<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Run only against a separate migrated test database. */
class AdminCancellationTimeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_cannot_cancel_at_or_after_start(): void
    {
        $this->freezeTime(function ($now) {
            $admin = User::factory()->create(['role' => 'admin']);
            Sanctum::actingAs($admin);
            $pitch = DB::table('pitches')->insertGetId([
                'name' => 'Test '.Str::random(8), 'type' => '5', 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ([0, -60] as $offset) {
                $start = $now->copy()->addMinutes($offset);
                $id = DB::table('bookings')->insertGetId([
                    'booking_code' => 'TEST'.Str::random(16), 'pitch_id' => $pitch,
                    'customer_name' => 'Test', 'customer_phone' => '0900000000',
                    'start_at' => $start, 'end_at' => $start->copy()->addHour(),
                    'status' => 'confirmed', 'source' => 'web', 'pitch_price' => 200000,
                    'service_total' => 0, 'total_amount' => 200000,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $this->patchJson("/api/admin/bookings/{$id}/cancel")
                    ->assertUnprocessable()->assertJsonValidationErrors('start_at');
                $this->assertDatabaseHas('bookings', ['id' => $id, 'status' => 'confirmed']);
            }
        });
    }
}
