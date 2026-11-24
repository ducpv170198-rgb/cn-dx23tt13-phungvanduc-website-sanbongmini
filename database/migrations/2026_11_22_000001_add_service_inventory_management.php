<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('low_stock_threshold')->default(10);
        });
        Schema::create('service_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('action', 24);
            $table->integer('quantity_change');
            $table->unsignedInteger('stock_before')->nullable();
            $table->unsignedInteger('stock_after');
            $table->string('note', 300)->nullable();
            $table->timestamp('created_at');
            $table->index(['service_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_stock_movements');
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('low_stock_threshold'));
    }
};
