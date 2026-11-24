<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('friendly_matches', function (Blueprint $table) {
            $table->id();
            $table->string('team_name', 120);
            $table->string('contact_name', 120);
            $table->string('contact_phone', 30);
            $table->date('match_date');
            $table->time('match_time');
            $table->unsignedTinyInteger('pitch_type');
            $table->string('level', 30);
            $table->string('location_note', 180)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('owner_token_hash', 64);
            $table->timestamps();
            $table->index(['status', 'match_date', 'pitch_type']);
        });
        Schema::create('friendly_match_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('friendly_match_id')->constrained('friendly_matches')->cascadeOnDelete();
            $table->string('team_name', 120);
            $table->string('contact_name', 120);
            $table->string('contact_phone', 30);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['friendly_match_id', 'status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('friendly_match_requests');
        Schema::dropIfExists('friendly_matches');
    }
};
