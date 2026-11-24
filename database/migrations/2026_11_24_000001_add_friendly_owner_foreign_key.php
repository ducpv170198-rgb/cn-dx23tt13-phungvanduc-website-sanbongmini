<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (DB::table('friendly_matches')->whereNotNull('owner_user_id')->whereNotIn('owner_user_id', DB::table('users')->select('id'))->exists()) {
            throw new RuntimeException('Orphan friendly match owners: review and repair before migration.');
        }
        Schema::table('friendly_matches', function (Blueprint $table) {
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('friendly_matches', fn (Blueprint $table) => $table->dropForeign(['owner_user_id']));
    }
};
