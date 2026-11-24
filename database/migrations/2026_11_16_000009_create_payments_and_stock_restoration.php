<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('stock_restored_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->enum('kind', ['receipt', 'refund']);
            $table->enum('method', ['cash', 'bank_transfer', 'vietqr', 'other']);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 120)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['invoice_id', 'kind']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('payments');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn(['stock_restored_at', 'cancelled_at']));
    }
};
