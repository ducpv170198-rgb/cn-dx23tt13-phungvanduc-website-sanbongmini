<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('friendly_match_requests', function (Blueprint $table) {
            // Nullable để giữ nguyên các lời mời cũ được gửi khi chưa đăng nhập.
            $table->foreignId('sender_user_id')->nullable()->after('friendly_match_id')
                ->constrained('users')->nullOnDelete();
            $table->unique(['friendly_match_id', 'sender_user_id'], 'friendly_request_member_unique');
        });
    }

    public function down(): void
    {
        Schema::table('friendly_match_requests', function (Blueprint $table) {
            $table->dropUnique('friendly_request_member_unique');
            $table->dropConstrainedForeignId('sender_user_id');
        });
    }
};
