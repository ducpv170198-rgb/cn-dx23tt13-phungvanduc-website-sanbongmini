<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pitch_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pitch_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_type'); // 0: ngày thường, 1: cuối tuần
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('price_per_hour', 12, 2);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['pitch_id', 'day_type', 'start_time', 'end_time'], 'price_rule_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pitch_price_rules');
    }
};
