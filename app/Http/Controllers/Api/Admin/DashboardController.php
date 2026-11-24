<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate(['date' => ['sometimes', 'required', 'date_format:Y-m-d']]);
        $date = $input['date'] ?? now()->toDateString();

        $base = DB::table('bookings')->whereDate('start_at', $date);

        $bookings = (clone $base)->count();
        $confirmed = (clone $base)->whereIn('status', ['confirmed', 'completed'])->count();
        $revenue = DB::table('payments')->whereDate('created_at', $date)
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt' THEN amount ELSE -amount END), 0) as net")
            ->value('net');

        $active = DB::table('pitches')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'type']);
        $activePitches = $active->count();

        // Operating window 06:00–22:00, 16 hours per active pitch.
        // Count only confirmed/completed bookings, clipped to this day's window.
        // Merge overlapping intervals to avoid double counting.
        $windowStart = Carbon::parse($date . ' 06:00:00');
        $windowEnd = Carbon::parse($date . ' 22:00:00');
        $ids = $active->pluck('id')->all();
        $occupied = DB::table('bookings')
            ->whereIn('pitch_id', $ids)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('start_at', '<', $windowEnd->toDateTimeString())
            ->where('end_at', '>', $windowStart->toDateTimeString())
            ->orderBy('start_at')
            ->get(['pitch_id', 'start_at', 'end_at'])
            ->groupBy('pitch_id');
        $perPitch = [];
        $totalBookedMinutes = 0;
        foreach ($active as $pitch) {
            $intervals = [];
            foreach ($occupied->get($pitch->id, collect()) as $booking) {
                $start = max($windowStart->timestamp, Carbon::parse($booking->start_at)->timestamp);
                $end = min($windowEnd->timestamp, Carbon::parse($booking->end_at)->timestamp);
                if ($end > $start) $intervals[] = [$start, $end];
            }
            usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
            $merged = [];
            foreach ($intervals as [$start, $end]) {
                if ($merged && $start <= $merged[count($merged) - 1][1]) {
                    $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $end);
                } else {
                    $merged[] = [$start, $end];
                }
            }
            $minutes = (int) (array_sum(array_map(fn ($v) => ($v[1] - $v[0]) / 60, $merged)));
            $totalBookedMinutes += $minutes;
            $perPitch[] = [
                'pitch_id' => $pitch->id,
                'pitch_name' => $pitch->name,
                'pitch_type' => $pitch->type,
                'booked_minutes' => $minutes,
                'available_minutes' => 960,
                'occupancy_percent' => round($minutes / 960 * 100, 1),
            ];
        }
        $totalAvailableMinutes = $activePitches * 960;
        $occupancy = [
            'operating_from' => '06:00', 'operating_to' => '22:00',
            'booked_minutes' => $totalBookedMinutes,
            'available_minutes' => $totalAvailableMinutes,
            'occupancy_percent' => $totalAvailableMinutes ? round($totalBookedMinutes / $totalAvailableMinutes * 100, 1) : 0,
            'pitches' => $perPitch,
        ];

        return response()->json([
            'date' => $date,
            'bookings_total' => $bookings,
            'bookings_confirmed_or_completed' => $confirmed,
            'revenue_recorded' => (float) $revenue,
            'active_pitches' => $activePitches,
            'occupancy' => $occupancy,
        ]);
    }

    public function revenue(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $rows = DB::table('payments')
            ->selectRaw("DATE(created_at) as date, SUM(CASE WHEN kind = 'receipt' THEN amount ELSE -amount END) as revenue, COUNT(*) as transactions")
            ->whereBetween('created_at', [
                Carbon::parse($data['from'])->startOfDay(),
                Carbon::parse($data['to'])->endOfDay(),
            ])
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return response()->json(['from' => $data['from'], 'to' => $data['to'], 'rows' => $rows]);
    }
}
