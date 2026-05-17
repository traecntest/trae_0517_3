<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $query = Permission::orderBy('group')->orderBy('id');

        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        $permissions = $query->get()->groupBy('group');

        return response()->json([
            'code' => 0,
            'data' => $permissions,
        ]);
    }

    public function all()
    {
        $permissions = Permission::orderBy('group')->orderBy('id')->get();

        return response()->json([
            'code' => 0,
            'data' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:permissions|max:100',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'group' => 'string|max:50',
        ]);

        $permission = Permission::create($validated);

        return response()->json([
            'code' => 0,
            'message' => '创建成功',
            'data' => $permission,
        ]);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|unique:permissions,name,' . $id . '|max:100',
            'display_name' => 'string|max:100',
            'description' => 'nullable|string|max:255',
            'group' => 'string|max:50',
        ]);

        $permission->update($validated);

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => $permission,
        ]);
    }

    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);
        $permission->roles()->detach();
        $permission->delete();

        return response()->json([
            'code' => 0,
            'message' => '删除成功',
        ]);
    }
}
