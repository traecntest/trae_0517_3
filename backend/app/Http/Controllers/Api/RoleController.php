<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Permission;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $query = Role::with('permissions')->orderBy('id', 'desc');

        if ($request->has('keyword')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->keyword}%")
                    ->orWhere('display_name', 'like', "%{$request->keyword}%");
            });
        }

        $roles = $query->paginate($request->get('pageSize', 20));

        return response()->json([
            'code' => 0,
            'data' => $roles,
        ]);
    }

    public function all()
    {
        $roles = Role::where('status', 1)->get();

        return response()->json([
            'code' => 0,
            'data' => $roles,
        ]);
    }

    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $role,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles|max:50',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'status' => 'integer|in:0,1',
        ]);

        $role = Role::create($validated);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'code' => 0,
            'message' => '创建成功',
            'data' => $role->load('permissions'),
        ]);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|unique:roles,name,' . $id . '|max:50',
            'display_name' => 'string|max:100',
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'status' => 'integer|in:0,1',
        ]);

        $role->update($validated);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => $role->load('permissions'),
        ]);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return response()->json([
            'code' => 0,
            'message' => '删除成功',
        ]);
    }
}
