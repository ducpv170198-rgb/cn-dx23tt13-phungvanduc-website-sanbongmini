<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('recurring_booking_series', function(Blueprint $t) {
   $t->id(); $t->foreignId('pitch_id')->constrained()->restrictOnDelete();
   $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
   $t->string('customer_name',120); $t->string('customer_phone',30); $t->string('customer_email')->nullable();
   $t->date('first_date'); $t->unsignedTinyInteger('weeks'); $t->time('start_time'); $t->time('end_time');
   $t->timestamps();
  });
  Schema::table('bookings', function(Blueprint $t) {
   $t->foreignId('recurring_series_id')->nullable()->after('pitch_id')->constrained('recurring_booking_series')->restrictOnDelete();
   $t->unique(['recurring_series_id','start_at'],'recurring_occurrence_unique');
  });
 }
 public function down(): void {
  Schema::table('bookings', function(Blueprint $t) { $t->dropUnique('recurring_occurrence_unique'); $t->dropConstrainedForeignId('recurring_series_id'); });
  Schema::dropIfExists('recurring_booking_series');
 }
};
