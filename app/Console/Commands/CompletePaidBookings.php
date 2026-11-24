<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompletePaidBookings extends Command
{
    protected $signature = 'bookings:complete-paid';
    protected $description = 'Chuyển đơn đã xác nhận, đã kết thúc và thanh toán đủ sang hoàn tất';

    public function handle(): int
    {
        $count = app(\App\Services\BookingLifecycle::class)->completePaid();
        $this->info("Đã chuyển {$count} đơn sang Hoàn tất.");
        return self::SUCCESS;
    }
}
