<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PitchPriceRuleController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('pitch_price_rules')->orderBy('pitch_id')->orderBy('day_type')->orderBy('start_time');
        if ($request->filled('pitch_id')) {
            $request->validate(['pitch_id' => 'integer|exists:pitches,id']);
            $q->where('pitch_id', $request->integer('pitch_id'));
        }
        return response()->json($q->get());
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'pitch_id' => ['required', 'integer', 'exists:pitches,id'],
            'day_type' => ['required', 'integer', 'in:0,1'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'price_per_hour' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ]);
    }

    private function rejectOverlap(array $d, ?int $ignoreId = null): void
    {
        $q = DB::table('pitch_price_rules')
            ->where('pitch_id', $d['pitch_id'])
            ->where('day_type', $d['day_type'])
            ->where('start_time', '<', $d['end_time'])
            ->where('end_time', '>', $d['start_time']);
        if ($ignoreId !== null) $q->where('id', '<>', $ignoreId);
        $from = $d['effective_from'] ?? null;
        $to = $d['effective_to'] ?? null;
        if ($from !== null) $q->where(fn ($x) => $x->whereNull('effective_to')->orWhere('effective_to', '>=', $from));
        if ($to !== null) $q->where(fn ($x) => $x->whereNull('effective_from')->orWhere('effective_from', '<=', $to));
        if ($q->exists()) {
            throw ValidationException::withMessages(['start_time' => ['Khung giờ và khoảng ngày áp dụng trùng với một quy tắc giá đã có.']]);
        }
    }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        return DB::transaction(function () use ($d) {
            // Serialize changes to rules of the same pitch to avoid concurrent overlapping inserts.
            DB::table('pitches')->where('id', $d['pitch_id'])->lockForUpdate()->first();
            $this->rejectOverlap($d);
            $id = DB::table('pitch_price_rules')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
            return response()->json(DB::table('pitch_price_rules')->find($id), 201);
        });
    }

    public function update(Request $request, int $id)
    {
        $d = $this->validated($request);
        return DB::transaction(function () use ($d, $id) {
            $rule = DB::table('pitch_price_rules')->where('id', $id)->first();
            abort_unless($rule, 404);
            // Lock both pitch rows in a stable order if a rule is moved.
            foreach (array_unique(collect([$rule->pitch_id, $d['pitch_id']])->sort()->values()->all()) as $pitchId) {
                DB::table('pitches')->where('id', $pitchId)->lockForUpdate()->first();
            }
            $this->rejectOverlap($d, $id);
            DB::table('pitch_price_rules')->where('id', $id)->update($d + ['updated_at' => now()]);
            return response()->json(DB::table('pitch_price_rules')->find($id));
        });
    }

    public function destroy(int $id)
    {
        return DB::transaction(function () use ($id) {
            $rule = DB::table('pitch_price_rules')->where('id', $id)->first();
            abort_unless($rule, 404);
            DB::table('pitches')->where('id', $rule->pitch_id)->lockForUpdate()->first();
            DB::table('pitch_price_rules')->where('id', $id)->delete();
            return response()->json(['message' => 'Đã xóa quy tắc giá.']);
        });
    }
}
