<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Run only against a dedicated testing database with all migrations applied.
 * Uses transactions and does not call migrate:fresh or wipe live business data.
 */
class ServiceInventoryTest extends TestCase
{
    use DatabaseTransactions;

    private function loginAs(string $role): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        Sanctum::actingAs($user);
    }

    private function createService(string $name = 'Nước suối kiểm thử', ?int $stock = 10): int
    {
        return (int) DB::table('services')->insertGetId([
            'name' => $name, 'type' => 'drink', 'unit' => 'chai',
            'price' => 10000, 'stock' => $stock,
            'low_stock_threshold' => 3, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_guests_cannot_access_inventory(): void
    {
        $this->getJson('/api/admin/services')->assertUnauthorized();
    }

    public function test_customer_cannot_edit_inventory(): void
    {
        $this->loginAs('customer');
        $this->getJson('/api/admin/services')->assertForbidden();
    }

    public function test_admin_can_create_service_and_public_can_see_it(): void
    {
        $this->loginAs('admin');
        $name = 'Dịch vụ kiểm thử ' . uniqid();
        $response = $this->postJson('/api/admin/services', [
            'name' => $name, 'type' => 'drink', 'unit' => 'chai', 'price' => 12000,
            'stock' => 15, 'low_stock_threshold' => 5, 'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.name', $name);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('services', ['id' => $id, 'stock' => 15]);
        $this->assertDatabaseHas('service_stock_movements', [
            'service_id' => $id, 'action' => 'opening', 'stock_after' => 15,
        ]);
        $this->getJson('/api/services')->assertOk()->assertJsonFragment(['name' => $name]);
    }

    public function test_stock_receipt_and_issues_are_logged_and_cannot_go_negative(): void
    {
        $this->loginAs('staff');
        $id = $this->createService('Dịch vụ kiểm thử ' . uniqid());
        $this->postJson("/api/admin/services/{$id}/stock", [
            'operation' => 'in', 'quantity' => 5, 'reason' => 'Nhập thêm hàng',
        ])->assertOk()->assertJsonPath('data.stock_after', 15);
        $this->postJson("/api/admin/services/{$id}/stock", [
            'operation' => 'out', 'quantity' => 4, 'reason' => 'Hàng sử dụng nội bộ',
        ])->assertOk()->assertJsonPath('data.stock_after', 11);
        $this->postJson("/api/admin/services/{$id}/stock", [
            'operation' => 'out', 'quantity' => 12, 'reason' => 'Xuất vượt tồn kho',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('services', ['id' => $id, 'stock' => 11]);
        $this->assertSame(2, DB::table('service_stock_movements')->where('service_id', $id)->count());
    }

    public function test_unlimited_stock_can_be_switched_to_tracked_by_stocktake(): void
    {
        $this->loginAs('admin');
        $id = $this->createService('Dịch vụ kiểm thử ' . uniqid(), null);
        $this->postJson("/api/admin/services/{$id}/stock", [
            'operation' => 'in', 'quantity' => 2, 'reason' => 'Nhập thử',
        ])->assertUnprocessable();
        $this->postJson("/api/admin/services/{$id}/stock", [
            'operation' => 'set', 'quantity' => 7, 'reason' => 'Bắt đầu kiểm kê',
        ])->assertOk()->assertJsonPath('data.stock_after', 7);
        $this->assertDatabaseHas('services', ['id' => $id, 'stock' => 7]);
    }

    public function test_inactive_service_does_not_appear_in_public_catalog(): void
    {
        $this->loginAs('admin');
        $name = 'Dịch vụ ngừng bán ' . uniqid();
        $this->createService($name, 8);
        DB::table('services')->where('name', $name)->update(['is_active' => false]);
        $this->getJson('/api/services')->assertOk()->assertDontSee($name);
    }
}
