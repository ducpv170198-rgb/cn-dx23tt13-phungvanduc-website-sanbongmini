<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('friendly_matches', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_user_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('friendly_matches', function (Blueprint $table) {
            $table->dropColumn('owner_user_id');
        });
    }
};
