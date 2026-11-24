<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Chỉ chạy trên database KIỂM THỬ riêng đã migrate đầy đủ.
 * DatabaseTransactions không xóa dữ liệu, nhưng không được dùng production DB.
 */
class FriendlyMemberInvitationsTest extends TestCase
{
    use DatabaseTransactions;

    private function loginMember(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'customer'])->save();
        Sanctum::actingAs($user);
        return $user;
    }

    private function postMatch(): int
    {
        $result = $this->postJson('/api/friendly-matches', [
            'team_name' => 'Đội A kiểm thử', 'contact_name' => 'Đại diện A',
            'contact_phone' => '0900000001', 'match_date' => now()->addDays(2)->format('Y-m-d'),
            'match_time' => '19:00', 'pitch_type' => '5', 'level' => 'trung_binh',
        ])->assertCreated();
        return (int) $result->json('id');
    }

    private function requestData(): array
    {
        return [
            'team_name' => 'Đội B kiểm thử', 'contact_name' => 'Đại diện B',
            'contact_phone' => '0900000002', 'message' => 'Giao hữu nhé!',
        ];
    }

    public function test_owner_cannot_invite_self_even_via_api(): void
    {
        $this->loginMember();
        $id = $this->postMatch();
        $this->postJson("/api/friendly-matches/{$id}/requests", $this->requestData())
            ->assertUnprocessable()->assertJsonValidationErrors('match');
        $this->assertDatabaseMissing('friendly_match_requests', ['friendly_match_id' => $id]);
    }

    public function test_invitations_belong_to_sender_and_are_not_duplicated(): void
    {
        $owner = $this->loginMember();
        $id = $this->postMatch();
        $sender = $this->loginMember();
        $result = $this->postJson("/api/friendly-matches/{$id}/requests", $this->requestData())
            ->assertCreated();
        $reqId = (int) $result->json('request_id');
        $this->assertDatabaseHas('friendly_match_requests', [
            'id' => $reqId, 'friendly_match_id' => $id, 'sender_user_id' => $sender->id,
        ]);
        $this->postJson("/api/friendly-matches/{$id}/requests", $this->requestData())
            ->assertUnprocessable();
        $this->getJson('/api/friendly-match-requests/sent')->assertOk()
            ->assertJsonPath('data.0.id', $reqId)
            ->assertJsonMissingPath('data.0.host_contact_phone');
        Sanctum::actingAs($owner);
        $this->getJson('/api/friendly-match-requests/sent')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_owner_can_manage_received_requests_and_acceptance_shares_contact(): void
    {
        $owner = $this->loginMember();
        $id = $this->postMatch();
        $sender = $this->loginMember();
        $reqId = (int) $this->postJson("/api/friendly-matches/{$id}/requests", $this->requestData())
            ->assertCreated()->json('request_id');
        $this->postJson("/api/friendly-matches/{$id}/owner")->assertForbidden();
        $this->patchJson("/api/friendly-matches/{$id}/requests/{$reqId}", ['action' => 'accept'])
            ->assertForbidden();
        Sanctum::actingAs($owner);
        $this->getJson('/api/friendly-matches/mine')->assertOk()->assertJsonPath('data.0.pending_count', 1);
        $this->postJson("/api/friendly-matches/{$id}/owner")->assertOk()
            ->assertJsonPath('requests.0.id', $reqId);
        $this->patchJson("/api/friendly-matches/{$id}/requests/{$reqId}", ['action' => 'accept'])
            ->assertOk()->assertJsonPath('status', 'accepted');
        Sanctum::actingAs($sender);
        $this->getJson('/api/friendly-match-requests/sent')->assertOk()
            ->assertJsonPath('data.0.host_contact_phone', '0900000001');
    }

    public function test_only_sender_can_withdraw_pending_invitation(): void
    {
        $this->loginMember();
        $id = $this->postMatch();
        $sender = $this->loginMember();
        $reqId = (int) $this->postJson("/api/friendly-matches/{$id}/requests", $this->requestData())
            ->assertCreated()->json('request_id');
        $this->loginMember();
        $this->patchJson("/api/friendly-match-requests/{$reqId}/withdraw")->assertNotFound();
        Sanctum::actingAs($sender);
        $this->patchJson("/api/friendly-match-requests/{$reqId}/withdraw")
            ->assertOk();
        $this->assertDatabaseHas('friendly_match_requests', [
            'id' => $reqId, 'status' => 'withdrawn',
        ]);
    }

    public function test_guests_must_login_before_sending_invitations(): void
    {
        $this->loginMember();
        $id = $this->postMatch();
        auth()->guard('sanctum')->forgetUser();
        auth()->forgetGuards();
        $this->postJson("/api/friendly-matches/{$id}/requests", $this->requestData())
            ->assertUnauthorized();
    }
}
