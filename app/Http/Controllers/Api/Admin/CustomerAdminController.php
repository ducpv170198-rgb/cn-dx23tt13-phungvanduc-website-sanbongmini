<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAdminController extends Controller
{
    private function base()
    {
        $payments = DB::table('payments as pay')
            ->join('invoices as inv', 'inv.id', '=', 'pay.invoice_id')
            ->select('inv.booking_id')
            ->selectRaw("SUM(CASE WHEN pay.kind = 'receipt' THEN pay.amount ELSE -pay.amount END) AS net_paid")
            ->groupBy('inv.booking_id');

        return DB::table('bookings as b')
            ->leftJoinSub($payments, 'pmt', fn ($join) => $join->on('pmt.booking_id', '=', 'b.id'))
            ->whereNotNull('b.customer_phone')
            ->where('b.customer_phone', '<>', '');
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $q = $this->base()->select('b.customer_phone')
            ->selectRaw('MAX(b.customer_name) AS customer_name, COUNT(*) AS bookings_total')
            ->selectRaw("SUM(CASE WHEN b.status IN ('confirmed','completed') THEN 1 ELSE 0 END) AS confirmed_total")
            ->selectRaw("SUM(CASE WHEN b.status <> 'cancelled' THEN COALESCE(b.total_amount,0) ELSE 0 END) AS booking_value")
            ->selectRaw('SUM(COALESCE(pmt.net_paid,0)) AS net_paid')
            ->selectRaw('MAX(b.start_at) AS last_booking')
            ->groupBy('b.customer_phone');
        if (!empty($data['search'])) {
            $search = '%' . addcslashes($data['search'], '%_\\') . '%';
            $q->where(function ($query) use ($search) {
                $query->where('b.customer_phone', 'like', $search)
                    ->orWhere('b.customer_name', 'like', $search);
            });
        }
        return response()->json($q->orderByDesc('last_booking')->paginate(20));
    }

    public function show(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $q = $this->base()->where('b.customer_phone', $data['phone'])
            ->leftJoin('pitches as pitch', 'pitch.id', '=', 'b.pitch_id')
            ->select('b.id', 'b.booking_code', 'b.customer_name', 'b.customer_phone',
                'b.start_at', 'b.end_at', 'b.status', 'b.total_amount', 'pitch.name as pitch_name')
            ->selectRaw('COALESCE(pmt.net_paid,0) AS net_paid');
        return response()->json($q->orderByDesc('b.start_at')->paginate(20));
    }
}
