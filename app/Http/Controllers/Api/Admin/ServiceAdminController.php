<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Service catalog and stock management. All stock mutations are transactional,
 * locked, and recorded in service_stock_movements for auditability.
 */
class ServiceAdminController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:drink,jersey,equipment,other'],
            'status' => ['nullable', 'in:all,active,inactive,low,out'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = DB::table('services');
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where('name', 'like', '%' . $search . '%');
        }
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        switch ($filters['status'] ?? 'all') {
            case 'active': $query->where('is_active', true); break;
            case 'inactive': $query->where('is_active', false); break;
            case 'low':
                $query->whereNotNull('stock')->where('stock', '>', 0)
                    ->whereColumn('stock', '<=', 'low_stock_threshold');
                break;
            case 'out': $query->where('stock', 0); break;
        }
        $list = $query->orderBy('name')->orderBy('id')->paginate(20);
        $list->getCollection()->transform(function ($service) {
            $service->stock_status = $this->stockStatus($service);
            return $service;
        });

        $summary = DB::table('services')->selectRaw(
            'COUNT(*) AS total_services, ' .
            'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_services, ' .
            'SUM(CASE WHEN stock IS NOT NULL AND stock = 0 THEN 1 ELSE 0 END) AS out_of_stock, ' .
            'SUM(CASE WHEN stock IS NOT NULL AND stock > 0 AND stock <= low_stock_threshold THEN 1 ELSE 0 END) AS low_stock, ' .
            'COALESCE(SUM(CASE WHEN stock IS NOT NULL THEN stock * price ELSE 0 END), 0) AS inventory_value'
        )->first();
        return response()->json([
            'data' => $list->items(),
            'current_page' => $list->currentPage(),
            'last_page' => $list->lastPage(),
            'total' => $list->total(),
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('services', 'name')],
            'type' => ['required', 'in:drink,jersey,equipment,other'],
            'unit' => ['required', 'string', 'max:30'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'stock' => ['present', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $service = DB::transaction(function () use ($data, $request) {
            $now = now();
            $id = DB::table('services')->insertGetId($data + ['created_at' => $now, 'updated_at' => $now]);
            if ($data['stock'] !== null) {
                DB::table('service_stock_movements')->insert([
                    'service_id' => $id, 'user_id' => $request->user()->id,
                    'action' => 'opening', 'quantity_change' => (int) $data['stock'],
                    'stock_before' => 0, 'stock_after' => (int) $data['stock'],
                    'note' => 'Tồn kho ban đầu khi tạo dịch vụ', 'created_at' => $now,
                ]);
            }
            return DB::table('services')->find($id);
        }, 3);
        return response()->json(['message' => 'Đã tạo dịch vụ.', 'data' => $service], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('services', 'name')->ignore($id)],
            'type' => ['required', 'in:drink,jersey,equipment,other'],
            'unit' => ['required', 'string', 'max:30'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000000'],
            'is_active' => ['required', 'boolean'],
            // Stock is intentionally not editable here: use stockAdjust for a transaction log.
            'stock' => ['prohibited'],
        ]);
        if (!DB::table('services')->where('id', $id)->exists()) abort(404);
        DB::table('services')->where('id', $id)->update($data + ['updated_at' => now()]);
        return response()->json(['message' => 'Đã cập nhật dịch vụ.', 'data' => DB::table('services')->find($id)]);
    }

    public function adjustStock(Request $request, int $id)
    {
        $data = $request->validate([
            'operation' => ['required', 'in:in,out,set'],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
            'reason' => ['required', 'string', 'min:3', 'max:300'],
        ]);
        if ($data['operation'] !== 'set' && (int) $data['quantity'] === 0) {
            throw ValidationException::withMessages(['quantity' => 'Số lượng nhập/xuất phải lớn hơn 0.']);
        }

        $result = DB::transaction(function () use ($data, $request, $id) {
            $service = DB::table('services')->where('id', $id)->lockForUpdate()->first();
            if (!$service) abort(404);
            if ($service->stock === null && $data['operation'] !== 'set') {
                throw ValidationException::withMessages(['stock' => 'Dịch vụ không giới hạn tồn kho; hãy kiểm kê để bắt đầu theo dõi số lượng.']);
            }
            $before = $service->stock === null ? null : (int) $service->stock;
            $quantity = (int) $data['quantity'];
            $after = match ($data['operation']) {
                'in' => $before + $quantity,
                'out' => $before - $quantity,
                'set' => $quantity,
            };
            if ($after < 0) {
                throw ValidationException::withMessages(['quantity' => "Tồn kho chỉ còn {$before}, không đủ để xuất {$quantity}."]);
            }
            if ($after > 100000000) {
                throw ValidationException::withMessages(['quantity' => 'Tồn kho vượt giới hạn 100.000.000.']);
            }
            $now = now();
            DB::table('services')->where('id', $id)->update(['stock' => $after, 'updated_at' => $now]);
            DB::table('service_stock_movements')->insert([
                'service_id' => $id, 'user_id' => $request->user()->id,
                'action' => $data['operation'], 'quantity_change' => $after - ($before ?? 0),
                'stock_before' => $before, 'stock_after' => $after,
                'note' => trim($data['reason']), 'created_at' => $now,
            ]);
            return ['stock_before' => $before, 'stock_after' => $after, 'quantity_change' => $after - ($before ?? 0)];
        }, 3);
        return response()->json(['message' => 'Đã cập nhật tồn kho và lưu lịch sử.', 'data' => $result]);
    }

    public function movements(int $id)
    {
        if (!DB::table('services')->where('id', $id)->exists()) abort(404);
        return DB::table('service_stock_movements as m')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.service_id', $id)
            ->orderByDesc('m.id')
            ->select('m.id','m.service_id','m.booking_id','m.action','m.quantity_change',
                'm.stock_before','m.stock_after','m.note','m.created_at','u.name as actor_name')
            ->paginate(20);
    }

    private function stockStatus(object $service): string
    {
        if ($service->stock === null) return 'unlimited';
        if ((int) $service->stock === 0) return 'out';
        if ((int) $service->stock <= (int) $service->low_stock_threshold) return 'low';
        return 'ok';
    }
}
