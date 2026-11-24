<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingQuoteController extends Controller
{
    public function show(Request $request, BookingPricingService $pricing)
    {
        $data = $request->validate([
            'pitch_id' => ['required', 'integer', 'exists:pitches,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'services' => ['sometimes', 'array'],
            'services.*.service_id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'services.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);
        if ($start->second || $end->second) {
            throw ValidationException::withMessages(['start_at' => 'Thời gian phải theo mốc phút.']);
        }

        $pitch = DB::table('pitches')->where('id', $data['pitch_id'])
            ->where('status', 'active')->first();
        if (!$pitch) {
            throw ValidationException::withMessages(['pitch_id' => 'Sân không khả dụng.']);
        }

        $overlap = DB::table('bookings')->where('pitch_id', $pitch->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('start_at', '<', $end)->where('end_at', '>', $start)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_at' => 'Khung giờ đã được đặt.']);
        }

        $pitchPrice = $pricing->pitchPrice((int) $pitch->id, $start, $end);
        $serviceTotal = 0;
        $serviceLines = [];
        foreach (collect($data['services'] ?? [])->sortBy('service_id') as $item) {
            $service = DB::table('services')->where('id', $item['service_id'])
                ->where('is_active', true)->first();
            if (!$service) {
                throw ValidationException::withMessages(['services' => 'Dịch vụ không khả dụng.']);
            }
            $quantity = (int) $item['quantity'];
            if ($service->stock !== null && $service->stock < $quantity) {
                throw ValidationException::withMessages(['services' => "Không đủ tồn kho: {$service->name}."]);
            }
            $unit = (string) $service->price;
            if (!preg_match('/^\d+(?:\.0{1,2})?$/', $unit)) {
                throw ValidationException::withMessages(['services' => 'Đơn giá dịch vụ phải là VND nguyên.']);
            }
            $lineTotal = (int) $unit * $quantity;
            $serviceTotal += $lineTotal;
            $serviceLines[] = [
                'service_id' => (int) $service->id,
                'name' => $service->name,
                'quantity' => $quantity,
                'unit_price' => (int) $unit,
                'line_total' => $lineTotal,
            ];
        }

        return response()->json([
            'pitch_id' => (int) $pitch->id,
            'pitch_name' => $pitch->name,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
            'pitch_price' => $pitchPrice,
            'service_total' => $serviceTotal,
            'total_amount' => $pitchPrice + $serviceTotal,
            'services' => $serviceLines,
            'message' => 'Giá tạm tính. Giá và tình trạng sân được kiểm tra lại khi xác nhận đặt sân.',
        ]);
    }
}
