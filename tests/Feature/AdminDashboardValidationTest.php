<?php
namespace Tests\Feature;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class AdminDashboardValidationTest extends TestCase
{
    public function test_invalid_dates_are_rejected(): void
    {
        $admin = new User(['name'=>'Test','email'=>'admin@example.test']);
        $admin->forceFill(['id'=>1,'role'=>'admin']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/dashboard?date=2026-02-30')->assertUnprocessable();
        $this->getJson('/api/admin/reports/revenue?from=2026-11-24&to=2026-11-23')->assertUnprocessable();
    }
    public function test_customer_cannot_read_admin_dashboard(): void
    {
        $user = new User(['name'=>'Test','email'=>'customer@example.test']);
        $user->forceFill(['id'=>2,'role'=>'customer']);
        Sanctum::actingAs($user);
        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }
}
