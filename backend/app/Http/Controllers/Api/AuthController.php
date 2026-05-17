<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['用户名或密码错误'],
            ]);
        }

        if ($user->status != 1) {
            throw ValidationException::withMessages([
                'username' => ['账户已被禁用'],
            ]);
        }

        $token = Auth::guard('api')->login($user);

        return $this->respondWithToken($token, $user);
    }

    public function logout()
    {
        Auth::guard('api')->logout();

        return response()->json([
            'code' => 0,
            'message' => '退出成功',
        ]);
    }

    public function refresh()
    {
        $token = Auth::guard('api')->refresh();
        $user = Auth::guard('api')->user();

        return $this->respondWithToken($token, $user);
    }

    public function userInfo()
    {
        $user = Auth::guard('api')->user();
        $user->load('roles.permissions');

        $permissions = $user->roles->flatMap->permissions->pluck('name')->unique();

        return response()->json([
            'code' => 0,
            'data' => [
                'user' => $user,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $permissions,
            ],
        ]);
    }

    protected function respondWithToken($token, $user)
    {
        $user->load('roles');

        return response()->json([
            'code' => 0,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
                'user' => $user,
            ],
        ]);
    }
}
