<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles')->orderBy('id', 'desc');

        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('username', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('department')) {
            $query->where('department', $request->department);
        }

        $users = $query->paginate($request->get('pageSize', 20));

        return response()->json([
            'code' => 0,
            'data' => $users,
        ]);
    }

    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $user,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users,username|max:50',
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email|max:100',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'password' => 'required|string|min:6|max:255',
            'roles' => 'array',
            'status' => 'integer|in:0,1',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        if (isset($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        return response()->json([
            'code' => 0,
            'message' => '创建成功',
            'data' => $user->load('roles'),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'username' => 'string|unique:users,username,' . $id . '|max:50',
            'name' => 'string|max:100',
            'email' => 'email|unique:users,email,' . $id . '|max:100',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:6|max:255',
            'roles' => 'array',
            'status' => 'integer|in:0,1',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        if (isset($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => $user->load('roles'),
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'code' => 0,
            'message' => '删除成功',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($validated['old_password'], $user->password)) {
            return response()->json([
                'code' => 400,
                'message' => '原密码错误',
            ], 400);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json([
            'code' => 0,
            'message' => '密码修改成功',
        ]);
    }

    public function options()
    {
        $users = User::where('status', 1)->select('id', 'name', 'username', 'department')->get();

        return response()->json([
            'code' => 0,
            'data' => $users,
        ]);
    }
}
