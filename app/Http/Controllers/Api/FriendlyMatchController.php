<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FriendlyMatchController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'pitch_type' => ['nullable', Rule::in(['5', '7'])],
            'level' => ['nullable', Rule::in(['moi_choi', 'trung_binh', 'kha', 'tu_do'])],
        ]);

        $query = DB::table('friendly_matches')
            ->where('status', 'open')
            ->where('match_date', '>=', now('Asia/Ho_Chi_Minh')->toDateString());
        if (isset($filters['date'])) $query->where('match_date', $filters['date']);
        if (isset($filters['pitch_type'])) $query->where('pitch_type', (int) $filters['pitch_type']);
        if (isset($filters['level'])) $query->where('level', $filters['level']);

        // Chỉ công khai thông tin đội bóng. Không trả số điện thoại hay ID chủ tin.
        // is_owner cho phép ẩn nút "Gửi lời mời" trên chính tin của người dùng.
        $viewerId = $request->user('sanctum')?->id;
        $page = $query->select('id', 'team_name', 'match_date', 'match_time', 'pitch_type',
            'level', 'location_note', 'note', 'status', 'owner_user_id')
            ->orderBy('match_date')->orderBy('match_time')->paginate(20);
        $page->getCollection()->transform(function ($match) use ($viewerId) {
            $match->is_owner = $viewerId !== null
                && (int) $match->owner_user_id === (int) $viewerId;
            unset($match->owner_user_id);
            return $match;
        });
        return response()->json($page);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'team_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'match_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'match_time' => ['required', 'date_format:H:i'],
            'pitch_type' => ['required', Rule::in(['5', '7'])],
            'level' => ['required', Rule::in(['moi_choi', 'trung_binh', 'kha', 'tu_do'])],
            'location_note' => ['nullable', 'string', 'max:180'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $this->member($request);
        $id = DB::table('friendly_matches')->insertGetId(array_merge($data, [
            'owner_user_id' => $user->id,
            'owner_token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]));
        return response()->json(['message' => 'Đã đăng tin tìm đối.', 'id' => $id], 201);
    }

    public function myMatches(Request $request)
    {
        $user = $this->member($request);
        $page = DB::table('friendly_matches')
            ->where('owner_user_id', $user->id)
            ->select('id', 'team_name', 'match_date', 'match_time', 'pitch_type', 'level', 'status', 'created_at')
            ->selectSub(function ($query) {
                $query->from('friendly_match_requests')->selectRaw('COUNT(*)')
                    ->whereColumn('friendly_match_requests.friendly_match_id', 'friendly_matches.id')
                    ->where('friendly_match_requests.status', 'pending');
            }, 'pending_count')
            ->orderByDesc('id')->paginate(20);
        return response()->json($page);
    }

    public function sentRequests(Request $request)
    {
        $user = $this->member($request);
        $page = DB::table('friendly_match_requests as r')
            ->join('friendly_matches as m', 'm.id', '=', 'r.friendly_match_id')
            ->where('r.sender_user_id', $user->id)
            ->select('r.id', 'r.friendly_match_id', 'r.team_name', 'r.message',
                'r.status', 'r.created_at', 'm.team_name as host_team', 'm.match_date',
                'm.match_time', 'm.status as match_status', 'm.contact_name as host_contact_name',
                'm.contact_phone as host_contact_phone')
            ->orderByDesc('r.id')->paginate(20);
        $page->getCollection()->transform(function ($item) {
            // Không tiết lộ số điện thoại của chủ tin khi chưa chấp nhận lời mời.
            if ($item->status !== 'accepted') {
                unset($item->host_contact_name, $item->host_contact_phone);
            }
            return $item;
        });
        return response()->json($page);
    }

    public function sendRequest(Request $request, int $id)
    {
        $user = $this->member($request);
        $data = $request->validate([
            'team_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($id, $data, $user) {
            $match = DB::table('friendly_matches')->where('id', $id)->lockForUpdate()->first();
            if (!$match || $match->status !== 'open') abort(404, 'Tin không còn nhận lời mời.');
            if ($match->match_date < now('Asia/Ho_Chi_Minh')->toDateString()) {
                abort(422, 'Ngày giao hữu đã qua.');
            }
            if ((int) $match->owner_user_id === (int) $user->id) {
                throw ValidationException::withMessages([
                    'match' => 'Bạn không thể gửi lời mời cho chính tin mình đăng.',
                ]);
            }
            // Khóa tin trước khi kiểm tra và ghi. Unique index là lớp bảo vệ thứ hai.
            if (DB::table('friendly_match_requests')
                ->where('friendly_match_id', $id)->where('sender_user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'match' => 'Bạn đã gửi lời mời cho tin này. Hãy xem mục Lời mời đã gửi.',
                ]);
            }
            $requestId = DB::table('friendly_match_requests')->insertGetId(array_merge($data, [
                'friendly_match_id' => $id, 'sender_user_id' => $user->id,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]));
            return response()->json([
                'message' => 'Đã gửi lời mời. Bạn có thể theo dõi trong mục Lời mời đã gửi.',
                'request_id' => $requestId,
            ], 201);
        });
    }

    private function member(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['customer', 'admin', 'staff'], true),
            403, 'Vui lòng đăng nhập bằng tài khoản thành viên.');
        return $user;
    }

    private function ownedMatch(int $id, Request $request, bool $lock = false)
    {
        $query = DB::table('friendly_matches')->where('id', $id);
        if ($lock) $query->lockForUpdate();
        $match = $query->first();
        if (!$match) abort(404, 'Không tìm thấy tin.');
        $user = $this->member($request);
        // Mỗi tài khoản chỉ quản lý lời mời của tin do chính mình đăng.
        if ((int) ($match->owner_user_id ?? 0) !== (int) $user->id) {
            abort(403, 'Bạn không sở hữu tin này.');
        }
        return $match;
    }

    public function ownerView(Request $request, int $id)
    {
        $match = $this->ownedMatch($id, $request);
        return response()->json([
            'match' => [
                'id' => $match->id, 'team_name' => $match->team_name,
                'status' => $match->status,
            ],
            'requests' => DB::table('friendly_match_requests')
                ->where('friendly_match_id', $id)
                ->select('id', 'team_name', 'contact_name', 'contact_phone', 'message', 'status', 'created_at')
                ->orderByDesc('id')->get(),
        ]);
    }

    public function decide(Request $request, int $id, int $requestId)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['accept', 'reject'])]]);
        return DB::transaction(function () use ($id, $requestId, $data, $request) {
            $match = $this->ownedMatch($id, $request, true);
            if ($match->status !== 'open') {
                throw ValidationException::withMessages(['action' => 'Tin đã đóng.']);
            }
            $target = DB::table('friendly_match_requests')->where('friendly_match_id', $id)
                ->where('id', $requestId)->lockForUpdate()->first();
            if (!$target || $target->status !== 'pending') abort(404, 'Lời mời không còn chờ xử lý.');
            $status = $data['action'] === 'accept' ? 'accepted' : 'rejected';
            DB::table('friendly_match_requests')->where('id', $requestId)->update([
                'status' => $status, 'updated_at' => now(),
            ]);
            if ($status === 'accepted') {
                DB::table('friendly_matches')->where('id', $id)->update([
                    'status' => 'matched', 'updated_at' => now(),
                ]);
                DB::table('friendly_match_requests')->where('friendly_match_id', $id)
                    ->where('id', '!=', $requestId)->where('status', 'pending')
                    ->update(['status' => 'rejected', 'updated_at' => now()]);
            }
            return response()->json(['message' => 'Đã xử lý lời mời.', 'status' => $status]);
        });
    }

    public function withdrawRequest(Request $request, int $requestId)
    {
        $user = $this->member($request);
        DB::transaction(function () use ($requestId, $user) {
            $row = DB::table('friendly_match_requests')->where('id', $requestId)
                ->where('sender_user_id', $user->id)->first();
            if (!$row) abort(404, 'Không tìm thấy lời mời của bạn.');
            // Thứ tự khóa thống nhất với decide(): tin -> lời mời.
            DB::table('friendly_matches')->where('id', $row->friendly_match_id)->lockForUpdate()->first();
            $locked = DB::table('friendly_match_requests')->where('id', $requestId)
                ->where('sender_user_id', $user->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== 'pending') {
                throw ValidationException::withMessages(['request' => 'Lời mời không còn ở trạng thái chờ.']);
            }
            DB::table('friendly_match_requests')->where('id', $requestId)->update([
                'status' => 'withdrawn', 'updated_at' => now(),
            ]);
        });
        return response()->json(['message' => 'Đã rút lời mời.']);
    }

    public function cancel(Request $request, int $id)
    {
        DB::transaction(function () use ($id, $request) {
            $match = $this->ownedMatch($id, $request, true);
            if ($match->status !== 'open') {
                throw ValidationException::withMessages(['status' => 'Tin đã đóng.']);
            }
            DB::table('friendly_matches')->where('id', $id)->update([
                'status' => 'cancelled', 'updated_at' => now(),
            ]);
            DB::table('friendly_match_requests')->where('friendly_match_id', $id)
                ->where('status', 'pending')->update(['status' => 'rejected', 'updated_at' => now()]);
        });
        return response()->json(['message' => 'Đã hủy tin.']);
    }
}
