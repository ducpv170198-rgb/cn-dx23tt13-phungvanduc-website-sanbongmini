<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\BookingPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecurringBookingController extends Controller
{
    public function index()
    {
        return response()->json(DB::table('recurring_booking_series')->orderByDesc('id')->paginate(20));
    }

    public function show(int $id)
    {
        $series = DB::table('recurring_booking_series')->find($id);
        abort_unless($series, 404);
        return response()->json([
            'series' => $series,
            'bookings' => DB::table('bookings')->where('recurring_series_id', $id)->orderBy('start_at')->get(),
        ]);
    }

    public function store(Request $request, BookingPricingService $pricing)
    {
        $data = $request->validate([
            'pitch_id' => ['required','integer','exists:pitches,id'],
            'customer_name' => ['required','string','max:120'],
            'customer_phone' => ['required','string','max:30'],
            'customer_email' => ['nullable','email','max:255'],
            'first_date' => ['required','date_format:Y-m-d','after_or_equal:today'],
            'start_time' => ['required','date_format:H:i'],
            'end_time' => ['required','date_format:H:i','after:start_time'],
            'weeks' => ['required','integer','min:1','max:52'],
        ]);
        $first = CarbonImmutable::createFromFormat('!Y-m-d', $data['first_date']);
        $starts = [];
        for ($i=0; $i<(int)$data['weeks']; $i++) {
            $day = $first->addWeeks($i)->format('Y-m-d');
            $start = CarbonImmutable::parse($day.' '.$data['start_time'].':00');
            $end = CarbonImmutable::parse($day.' '.$data['end_time'].':00');
            if ($end->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages(['end_time'=>'Giờ kết thúc phải sau giờ bắt đầu trong cùng ngày.']);
            }
            $starts[] = [$start,$end];
        }
        $result = DB::transaction(function () use ($data,$starts,$pricing,$request) {
            // Serialize ALL writers for the same pitch: same lock order as BookingController.
            $pitch = DB::table('pitches')->where('id',$data['pitch_id'])
                ->where('status','active')->lockForUpdate()->first();
            if (!$pitch) throw ValidationException::withMessages(['pitch_id'=>'Sân không khả dụng.']);
            $prices = [];
            foreach ($starts as $i => [$start,$end]) {
                $overlap = DB::table('bookings')->where('pitch_id',$pitch->id)
                    ->whereIn('status',['pending','confirmed','completed'])
                    ->where('start_at','<',$end)->where('end_at','>',$start)->exists();
                if ($overlap) throw ValidationException::withMessages([
                    'first_date'=>'Trùng lịch ngày '.$start->format('d/m/Y').'; chưa tạo lượt nào.',
                ]);
                $prices[$i] = $pricing->pitchPrice((int)$pitch->id,$start->toMutable(),$end->toMutable());
            }
            $now = now();
            $seriesId = DB::table('recurring_booking_series')->insertGetId([
                'pitch_id'=>$pitch->id,'created_by'=>$request->user()->id,
                'customer_name'=>$data['customer_name'],'customer_phone'=>$data['customer_phone'],
                'customer_email'=>$data['customer_email']??null,'first_date'=>$data['first_date'],
                'weeks'=>$data['weeks'],'start_time'=>$data['start_time'],'end_time'=>$data['end_time'],
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            $ids = [];
            foreach ($starts as $i => [$start,$end]) {
                $price = $prices[$i];
                // Mỗi buổi trong chuỗi có một mã riêng, dùng chung cho đơn và hóa đơn.
                $sharedCode = 'FD'.$now->format('ymd').strtoupper(Str::random(12));
                $id = DB::table('bookings')->insertGetId([
                    'booking_code'=>$sharedCode,
                    'pitch_id'=>$pitch->id,'recurring_series_id'=>$seriesId,'customer_id'=>null,
                    'customer_name'=>$data['customer_name'],'customer_phone'=>$data['customer_phone'],
                    'customer_email'=>$data['customer_email']??null,'start_at'=>$start,'end_at'=>$end,
                    'status'=>'pending','source'=>'recurring','note'=>null,
                    'pitch_price'=>$price,'service_total'=>0,'total_amount'=>$price,
                    'created_by'=>$request->user()->id,'created_at'=>$now,'updated_at'=>$now,
                ]);
                $invoiceId = DB::table('invoices')->insertGetId([
                    'invoice_code'=>$sharedCode,
                    'booking_id'=>$id,'subtotal'=>$price,'discount'=>0,'total'=>$price,
                    'payment_status'=>'unpaid','created_at'=>$now,'updated_at'=>$now,
                ]);
                DB::table('invoice_items')->insert([
                    'invoice_id'=>$invoiceId,'description'=>'Thuê sân định kỳ: '.$pitch->name,
                    'quantity'=>1,'unit_price'=>$price,'line_total'=>$price,
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
                $ids[] = $id;
            }
            return ['series_id'=>$seriesId,'booking_ids'=>$ids,'occurrences'=>count($ids)];
        }, 3);
        return response()->json(['message'=>'Đã tạo lịch đặt cố định và hóa đơn chưa thanh toán.','data'=>$result],201);
    }
    // Hủy các buổi chưa bắt đầu trong chuỗi; không hủy nếu còn tiền đã thu.
    public function cancelFuture(Request $request, int $id)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $result = DB::transaction(function () use ($id, $data) {
            $series = DB::table('recurring_booking_series')->where('id', $id)->lockForUpdate()->first();
            abort_unless($series, 404);
            $items = DB::table('bookings')->where('recurring_series_id', $id)
                ->where('start_at', '>', now())
                ->whereIn('status', ['pending', 'confirmed'])
                ->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                return ['cancelled' => 0, 'message' => 'Không có buổi tương lai đủ điều kiện hủy.'];
            }
            $ids = $items->pluck('id')->all();
            $invoices = DB::table('invoices')->whereIn('booking_id', $ids)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('booking_id');
            foreach ($items as $booking) {
                $invoice = $invoices->get($booking->id);
                if (!$invoice) throw ValidationException::withMessages(['invoice' => 'Buổi #'.$booking->id.' chưa có hóa đơn.']);
                $net = (int) DB::table('payments')->where('invoice_id', $invoice->id)
                    ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt' THEN amount ELSE -amount END), 0) AS net")
                    ->value('net');
                if ($net > 0) throw ValidationException::withMessages([
                    'payment' => 'Buổi #'.$booking->id.' đã thu tiền; hãy hoàn tiền trước. Chưa hủy buổi nào.'
                ]);
            }
            // Khóa các dòng dịch vụ theo thứ tự ID để hoàn kho an toàn.
            $lines = DB::table('booking_services')->whereIn('booking_id', $ids)->orderBy('service_id')->get();
            $stock = $lines->filter(fn ($line) => $items->firstWhere('id', $line->booking_id)?->stock_restored_at === null)
                ->groupBy('service_id')->map(fn ($group) => $group->sum('quantity'));
            foreach ($stock as $serviceId => $quantity) {
                $service = DB::table('services')->where('id', $serviceId)->lockForUpdate()->first();
                if (!$service) throw ValidationException::withMessages(['services' => 'Không tìm thấy dịch vụ #'.$serviceId]);
                if ($service->stock !== null) DB::table('services')->where('id', $serviceId)->increment('stock', $quantity);
            }
            foreach ($items as $booking) {
                DB::table('bookings')->where('id', $booking->id)->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'stock_restored_at' => $booking->stock_restored_at ?? now(),
                    'note' => trim(($booking->note ? $booking->note."\n" : '') . 'Hủy chuỗi: '.($data['reason'] ?? 'Không cung cấp')),
                    'updated_at' => now(),
                ]);
            }
            return ['cancelled' => count($ids), 'booking_ids' => $ids];
        }, 3);
        return response()->json($result);
    }

}
