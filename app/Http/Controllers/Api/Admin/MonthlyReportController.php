<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonthlyReportController extends Controller
{
    public function show(Request $request)
    {
        $input = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $start = Carbon::createFromFormat('!Y-m-d', $input['month'].'-01');
        $end = $start->copy()->endOfMonth();
        $from = $start->toDateTimeString();
        $until = $end->copy()->addDay()->startOfDay()->toDateTimeString();

        // Assumption: active pitches at query time were available throughout the month.
        // Historical maintenance schedules are not available in the current schema.
        $pitchIds = DB::table('pitches')->where('status', 'active')->pluck('id')->all();
        $bookings = DB::table('bookings')
            ->whereIn('pitch_id', $pitchIds)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('start_at', '<', $until)
            ->where('end_at', '>', $from)
            ->get(['id', 'pitch_id', 'start_at', 'end_at']);

        $intervals = [];
        $bookingIds = [];
        foreach ($bookings as $b) {
            $bookingIds[$b->id] = true;
            $a = Carbon::parse($b->start_at);
            $z = Carbon::parse($b->end_at);
            // Split multi-day bookings into individual operating-day intervals.
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $open = $day->copy()->setTime(6, 0);
                $close = $day->copy()->setTime(22, 0);
                $left = max($a->timestamp, $open->timestamp);
                $right = min($z->timestamp, $close->timestamp);
                if ($right > $left) $intervals[$b->pitch_id.'|'.$day->toDateString()][] = [$left, $right];
            }
        }
        $occupiedSeconds = 0;
        foreach ($intervals as $parts) {
            usort($parts, fn ($a, $b) => $a[0] <=> $b[0]);
            $merged = [];
            foreach ($parts as [$a, $z]) {
                $last = count($merged)-1;
                if ($last >= 0 && $a <= $merged[$last][1]) $merged[$last][1] = max($z, $merged[$last][1]);
                else $merged[] = [$a, $z];
            }
            foreach ($merged as [$a, $z]) $occupiedSeconds += $z-$a;
        }
        $capacityHours = count($pitchIds) * $start->daysInMonth * 16;
        $bookedHours = round($occupiedSeconds/3600, 2);

        // Cashflow in this month (receipt minus refund), allocated proportionally
        // between pitch and service amounts of the corresponding invoice.
        // This is not a ledger-level earmarking of individual transfers.
        $transactions = DB::table('payments as pay')
            ->join('invoices as i', 'i.id', '=', 'pay.invoice_id')
            ->join('bookings as b', 'b.id', '=', 'i.booking_id')
            ->where('pay.created_at', '>=', $from)
            ->where('pay.created_at', '<', $until)
            ->whereIn('pay.kind', ['receipt', 'refund'])
            ->get(['pay.kind', 'pay.amount', 'i.total', 'b.pitch_price', 'b.service_total']);
        $net = 0.0; $pitchRevenue = 0.0; $serviceRevenue = 0.0;
        foreach ($transactions as $t) {
            $signed = ($t->kind === 'refund' ? -1 : 1) * (float) $t->amount;
            $pitch = max(0, (float) $t->pitch_price);
            $service = max(0, (float) $t->service_total);
            $sum = $pitch + $service;
            $share = $sum > 0 ? $pitch/$sum : 1.0;
            $net += $signed;
            $pitchRevenue += $signed*$share;
            $serviceRevenue += $signed*(1-$share);
        }
        return response()->json([
            'month' => $input['month'],
            'bookings_count' => count($bookingIds),
            'booked_hours' => $bookedHours,
            'capacity_hours' => $capacityHours,
            'occupancy_percent' => $capacityHours ? round($bookedHours/$capacityHours*100, 1) : null,
            'active_pitches' => count($pitchIds),
            'capacity_note' => 'Giả định sân đang hoạt động đã mở cửa 06:00–22:00 mỗi ngày trong tháng; chưa trừ lịch bảo trì lịch sử.',
            'revenue_net' => round($net, 2),
            'pitch_revenue' => round($pitchRevenue, 2),
            'service_revenue' => round($serviceRevenue, 2),
            'revenue_note' => 'Tiền thực thu trừ hoàn tiền trong tháng, phân bổ theo tỷ trọng tiền sân và dịch vụ trên hóa đơn. Không phải doanh thu dồn tích.',
        ]);
    }
}
