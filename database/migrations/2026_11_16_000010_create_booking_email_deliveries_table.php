<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('booking_email_deliveries', function(Blueprint $t) {
  $t->id(); $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
  $t->string('kind',32); $t->string('recipient',255); $t->string('dedupe_key',150)->unique();
  $t->string('status',20)->default('queued'); $t->timestamp('sent_at')->nullable(); $t->timestamps();
 }); }
 public function down(): void { Schema::dropIfExists('booking_email_deliveries'); }
};
