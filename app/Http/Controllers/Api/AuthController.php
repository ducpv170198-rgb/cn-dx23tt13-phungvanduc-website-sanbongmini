<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        $email = Str::lower(trim($data['email']));
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => ['Email đã được sử dụng.']]);
        }

        // Never accept a role from the client. Public registration is customer-only.
        $user = new User();
        $user->name = trim($data['name']);
        $user->email = $email;
        $user->phone = $data['phone'] ?? null;
        $user->password = Hash::make($data['password']);
        $user->role = 'customer';
        $user->save();

        return response()->json([
            'message' => 'Tạo tài khoản thành viên thành công.',
            'user' => $this->publicUser($user),
            'token' => $user->createToken('fieldly-web')->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', Str::lower(trim($data['email'])))->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không chính xác.'],
            ]);
        }

        return response()->json([
            'message' => 'Đăng nhập thành công.',
            'user' => $this->publicUser($user),
            'token' => $user->createToken('fieldly-web')->plainTextToken,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->publicUser($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    private function publicUser(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role']);
    }
}
