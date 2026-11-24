<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PitchController extends Controller
{
    public function index()
    {
        return response()->json(DB::table('pitches')->orderBy('name')->paginate(20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:5,7'],
            'surface' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:active,maintenance,inactive'],
        ]);

        $id = DB::table('pitches')->insertGetId($data + [
            'status' => $data['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('pitches')->find($id), 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'type' => ['sometimes', 'required', 'in:5,7'],
            'surface' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:active,maintenance,inactive'],
        ]);

        abort_unless(DB::table('pitches')->where('id', $id)->exists(), 404);
        DB::table('pitches')->where('id', $id)->update($data + ['updated_at' => now()]);

        return response()->json(DB::table('pitches')->find($id));
    }
}
