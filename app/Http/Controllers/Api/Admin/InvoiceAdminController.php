<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceAdminController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'include_cancelled' => ['sometimes', 'boolean'],
        ]);
        $payments = DB::table('payments')
            ->select('invoice_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt' THEN amount WHEN kind = 'refund' THEN -amount ELSE 0 END), 0) AS net_paid")
            ->groupBy('invoice_id');
        $query = DB::table('invoices as i')
            ->join('bookings as b', 'b.id', '=', 'i.booking_id')
            ->leftJoin('pitches as p', 'p.id', '=', 'b.pitch_id')
            ->leftJoinSub($payments, 'pay', fn ($join) => $join->on('pay.invoice_id', '=', 'i.id'))
            ->select('i.*', 'b.status as booking_status', 'b.start_at', 'b.end_at',
                'b.customer_name', 'b.customer_phone', 'b.booking_code',
                'b.pitch_price', 'b.service_total', 'b.total_amount as booking_total', 'p.name as pitch_name')
            ->selectRaw('COALESCE(pay.net_paid, 0) AS net_paid')
            ->selectRaw('GREATEST(0, i.total - COALESCE(pay.net_paid, 0)) AS remaining');
        // Hóa đơn của đơn đã hủy chỉ ẩn khỏi danh sách, không xóa lịch sử giao dịch.
        if (!($filters['include_cancelled'] ?? false)) {
            $query->where('b.status', '<>', 'cancelled');
        }
        if (!empty($filters['search'])) {
            $search = '%' . addcslashes($filters['search'], '%_\\') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('b.customer_name', 'like', $search)
                    ->orWhere('b.customer_phone', 'like', $search)
                    ->orWhere('b.booking_code', 'like', $search)
                    ->orWhere('i.invoice_code', 'like', $search)
                    ->orWhere('p.name', 'like', $search);
            });
        }
        return response()->json($query->orderByDesc('i.id')->paginate((int) ($filters['per_page'] ?? 30)));
    }

    public function show(int $id)
    {
        $invoice = DB::table('invoices as i')
            ->join('bookings as b', 'b.id', '=', 'i.booking_id')
            ->leftJoin('pitches as p', 'p.id', '=', 'b.pitch_id')
            ->where('i.id', $id)
            ->select('i.*', 'b.status as booking_status', 'b.start_at', 'b.end_at',
                'b.customer_name', 'b.customer_phone', 'b.booking_code',
                'b.pitch_price', 'b.service_total', 'b.total_amount as booking_total', 'p.name as pitch_name')->first();
        if (!$invoice) abort(404);
        $payments = DB::table('payments')->where('invoice_id', $id)->orderByDesc('id')->get();
        $netPaid = $payments->sum(fn ($p) => $p->kind === 'receipt' ? (int) $p->amount : -(int) $p->amount);
        return response()->json(['invoice' => $invoice, 'payments' => $payments,
            'net_paid' => $netPaid, 'remaining' => max(0, (int) $invoice->total - $netPaid)]);
    }
}
