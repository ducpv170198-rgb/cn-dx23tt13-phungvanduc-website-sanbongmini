<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingPricingService
{
    /**
     * Calculate in integer VND. Every minute must be covered by exactly one rule.
     * Cross-midnight bookings are deliberately rejected in this version.
     */
    public function pitchPrice(int $pitchId, Carbon $start, Carbon $end): int
    {
        if ($start->toDateString() !== $end->toDateString()) {
            throw ValidationException::withMessages(['end_at' => 'Phiên bản này chưa hỗ trợ đặt sân qua nửa đêm.']);
        }
        if ($start->greaterThanOrEqualTo($end) || $start->isPast()) {
            throw ValidationException::withMessages(['start_at' => 'Thời gian đặt sân không hợp lệ hoặc đã qua.']);
        }
        if ($start->format('H:i:s') < '06:00:00' || $end->format('H:i:s') > '22:00:00' || $start->second || $end->second) {
            throw ValidationException::withMessages(['start_at' => 'Chỉ đặt sân trong giờ 06:00–22:00, theo mốc phút.']);
        }
        $dayType = $start->isWeekend() ? 1 : 0;
        $rules = DB::table('pitch_price_rules')
            ->where('pitch_id', $pitchId)->where('day_type', $dayType)
            ->where(function ($q) use ($start) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $start->toDateString());
            })->where(function ($q) use ($start) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start->toDateString());
            })->get();

        $startMinute = $start->hour * 60 + $start->minute;
        $endMinute = $end->hour * 60 + $end->minute;
        $boundaries = [$startMinute, $endMinute];
        foreach ($rules as $rule) {
            $a = $this->minute($rule->start_time);
            $b = $this->minute($rule->end_time);
            if ($a >= $b) {
                throw ValidationException::withMessages(['start_at' => 'Quy tắc giá có khung giờ không hợp lệ.']);
            }
            if ($a > $startMinute && $a < $endMinute) $boundaries[] = $a;
            if ($b > $startMinute && $b < $endMinute) $boundaries[] = $b;
        }
        $boundaries = array_values(array_unique($boundaries));
        sort($boundaries);
        $total = 0;
        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            [$a, $b] = [$boundaries[$i], $boundaries[$i + 1]];
            $matching = $rules->filter(fn ($r) => $this->minute($r->start_time) <= $a && $this->minute($r->end_time) >= $b);
            if ($matching->count() !== 1) {
                throw ValidationException::withMessages(['start_at' => 'Bảng giá thiếu hoặc trùng quy tắc trong khoảng thời gian đã chọn.']);
            }
            $price = (string) $matching->first()->price_per_hour;
            if (!preg_match('/^\d+(?:\.0{1,2})?$/', $price)) {
                throw ValidationException::withMessages(['start_at' => 'Đơn giá phải là số VND nguyên không âm.']);
            }
            $hourlyVnd = (int) $price;
            // Round each segment to nearest VND; define this as billing policy.
            $total += intdiv($hourlyVnd * ($b - $a) + 30, 60);
        }
        return $total;
    }

    private function minute(string $time): int
    {
        $parts = explode(':', $time);
        return (int) $parts[0] * 60 + (int) $parts[1];
    }
}
