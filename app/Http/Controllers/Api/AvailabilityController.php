<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AvailabilityController extends Controller
{
    public function index(Request $request, BookingPricingService $pricing)
    {
        $input = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', 'in:5,7'],
        ]);
        $tz = 'Asia/Ho_Chi_Minh';
        $nowVn = Carbon::now($tz);
        $date = Carbon::createFromFormat('!Y-m-d', $input['date'], $tz)->startOfDay();
        if ($date->isBefore($nowVn->copy()->startOfDay()) || $date->greaterThan($nowVn->copy()->addMonths(3))) {
            throw ValidationException::withMessages(['date' => 'Chỉ xem lịch từ hôm nay đến 3 tháng tới.']);
        }
        $pitches = DB::table('pitches')->where('status', 'active')->where('type', $input['type'])->orderBy('name')->get(['id','name','type']);
        $bookings = DB::table('bookings')->whereIn('pitch_id', $pitches->pluck('id'))
            ->whereIn('status', ['pending','confirmed','completed'])
            ->where('start_at', '<', $date->copy()->addDay())
            ->where('end_at', '>', $date)->get(['pitch_id','start_at','end_at']);
        $slots = [];
        foreach ($pitches as $pitch) {
            for ($minuteOfDay = 6 * 60; $minuteOfDay < 22 * 60; $minuteOfDay += 30) {
                $start = $date->copy()->setTime(intdiv($minuteOfDay, 60), $minuteOfDay % 60);
                $end = $start->copy()->addMinutes(30);
                $busy = $bookings->contains(fn ($b) => (int)$b->pitch_id === (int)$pitch->id && $b->start_at < $end->format('Y-m-d H:i:s') && $b->end_at > $start->format('Y-m-d H:i:s'));
                // Expose why a slot is unavailable without changing booking rules.
                $status = $busy ? 'booked' : ($start->greaterThan($nowVn) ? 'available' : 'past');
                $price = null;
                if ($status === 'available') {
                    try {
                        $price = $pricing->pitchPrice((int) $pitch->id, $start, $end);
                    } catch (ValidationException $e) {
                        $status = 'pricing_unavailable';
                    }
                }
                $available = $status === 'available';
                $slots[] = [
                    'pitch_id' => $pitch->id, 'start_at' => $start->format('Y-m-d H:i:s'),
                    'end_at' => $end->format('Y-m-d H:i:s'),
                    'available' => $available, 'price' => $price, 'status' => $status,
                ];
            }
        }
        return response()->json(['date' => $input['date'], 'pitches' => $pitches, 'slots' => $slots]);
    }

    public function services()
    {
        return response()->json(DB::table('services')->where('is_active', true)
            ->orderBy('name')->get(['id','name','type','unit','price','stock']));
    }
}
