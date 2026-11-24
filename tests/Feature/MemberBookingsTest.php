<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Chỉ chạy với database KIỂM THỬ riêng; không chạy trên database sanbong_mini thật. */
class MemberBookingsTest extends TestCase
{
    use DatabaseTransactions;

    private function login(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'customer'])->save();
        Sanctum::actingAs($user);
        return $user;
    }

    private function makeBooking(?User $owner, string $status = 'pending', int $payment = 0): array
    {
        $now = now();
        $pitchId = DB::table('pitches')->insertGetId([
            'name' => 'Sân test ' . Str::random(8), 'type' => '5',
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $code = 'TEST' . strtoupper(Str::random(16));
        $bookingId = DB::table('bookings')->insertGetId([
            'booking_code' => $code, 'pitch_id' => $pitchId, 'customer_id' => $owner?->id,
            'customer_name' => $owner?->name ?? 'Khách chưa đăng nhập',
            'customer_phone' => '0900000000', 'customer_email' => $owner?->email ?? null,
            'start_at' => $now->copy()->addDays(3)->startOfDay()->addHours(18),
            'end_at' => $now->copy()->addDays(3)->startOfDay()->addHours(19),
            'status' => $status, 'source' => 'web', 'pitch_price' => 500000,
            'service_total' => 0, 'total_amount' => 500000,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $invoiceId = DB::table('invoices')->insertGetId([
            'invoice_code' => $code, 'booking_id' => $bookingId,
            'subtotal' => 500000, 'discount' => 0, 'total' => 500000,
            'payment_status' => $payment ? 'deposit_paid' : 'unpaid',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('invoice_items')->insert([
            'invoice_id' => $invoiceId, 'description' => 'Thuê sân',
            'quantity' => 1, 'unit_price' => 500000, 'line_total' => 500000,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        if ($payment > 0) {
            DB::table('payments')->insert([
                'invoice_id' => $invoiceId, 'idempotency_key' => 'TEST-' . Str::uuid(),
                'kind' => 'receipt', 'method' => 'vietqr', 'amount' => $payment,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        return compact('bookingId', 'invoiceId', 'code');
    }

    public function test_guest_must_login_to_manage_bookings(): void
    {
        $this->getJson('/api/my-bookings')->assertUnauthorized();
        $this->getJson('/api/my-bookings/1')->assertUnauthorized();
    }

    public function test_member_only_sees_their_own_orders(): void
    {
        $first = $this->login();
        $mine = $this->makeBooking($first, 'pending', 100000);
        $second = $this->login();
        $other = $this->makeBooking($second);
        $this->makeBooking(null); // guest booking has no customer_id

        Sanctum::actingAs($first);
        $response = $this->getJson('/api/my-bookings')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $mine['bookingId'])
            ->assertJsonPath('data.0.can_cancel', false)
            ->assertDontSee($other['code']);
        $this->assertSame(100000, (int) $response->json('data.0.paid_amount'));
        $this->assertSame(400000, (int) $response->json('data.0.remaining_amount'));
    }

    public function test_details_cannot_be_read_by_another_member(): void
    {
        $first = $this->login();
        $mine = $this->makeBooking($first);
        $this->getJson('/api/my-bookings/' . $mine['bookingId'])
            ->assertOk()->assertJsonPath('data.booking_code', $mine['code'])
            ->assertJsonCount(1, 'items');

        $this->login();
        $this->getJson('/api/my-bookings/' . $mine['bookingId'])->assertNotFound();
        $this->patchJson('/api/my-bookings/' . $mine['bookingId'] . '/cancel')->assertNotFound();
    }

    public function test_member_can_cancel_unpaid_pending_order(): void
    {
        $owner = $this->login();
        $mine = $this->makeBooking($owner);
        $this->getJson('/api/my-bookings/' . $mine['bookingId'])
            ->assertJsonPath('data.can_cancel', true);
        $this->patchJson('/api/my-bookings/' . $mine['bookingId'] . '/cancel')
            ->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertDatabaseHas('bookings', [
            'id' => $mine['bookingId'], 'status' => 'cancelled',
        ]);
    }

    public function test_paid_or_confirmed_order_cannot_be_cancelled_by_member(): void
    {
        $owner = $this->login();
        $paid = $this->makeBooking($owner, 'pending', 100000);
        $confirmed = $this->makeBooking($owner, 'confirmed');
        $this->patchJson('/api/my-bookings/' . $paid['bookingId'] . '/cancel')->assertUnprocessable();
        $this->patchJson('/api/my-bookings/' . $confirmed['bookingId'] . '/cancel')->assertUnprocessable();
    }

    public function test_status_filter_only_affects_authenticated_member(): void
    {
        $owner = $this->login();
        $this->makeBooking($owner, 'pending');
        $confirmed = $this->makeBooking($owner, 'confirmed');
        $this->getJson('/api/my-bookings?status=confirmed')
            ->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $confirmed['bookingId']);
        $this->getJson('/api/my-bookings?status=bogus')->assertUnprocessable();
    }
}
