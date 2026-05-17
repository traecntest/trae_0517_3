<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'user:view', 'display_name' => '查看用户', 'group' => '用户管理'],
            ['name' => 'user:create', 'display_name' => '创建用户', 'group' => '用户管理'],
            ['name' => 'user:update', 'display_name' => '编辑用户', 'group' => '用户管理'],
            ['name' => 'user:delete', 'display_name' => '删除用户', 'group' => '用户管理'],
            ['name' => 'role:view', 'display_name' => '查看角色', 'group' => '角色管理'],
            ['name' => 'role:create', 'display_name' => '创建角色', 'group' => '角色管理'],
            ['name' => 'role:update', 'display_name' => '编辑角色', 'group' => '角色管理'],
            ['name' => 'role:delete', 'display_name' => '删除角色', 'group' => '角色管理'],
            ['name' => 'workflow:view', 'display_name' => '查看流程', 'group' => '流程管理'],
            ['name' => 'workflow:create', 'display_name' => '创建流程', 'group' => '流程管理'],
            ['name' => 'workflow:update', 'display_name' => '编辑流程', 'group' => '流程管理'],
            ['name' => 'workflow:delete', 'display_name' => '删除流程', 'group' => '流程管理'],
            ['name' => 'workflow:design', 'display_name' => '流程设计', 'group' => '流程管理'],
            ['name' => 'workflow:publish', 'display_name' => '发布流程', 'group' => '流程管理'],
            ['name' => 'instance:view', 'display_name' => '查看实例', 'group' => '流程实例'],
            ['name' => 'instance:create', 'display_name' => '发起流程', 'group' => '流程实例'],
            ['name' => 'instance:cancel', 'display_name' => '取消流程', 'group' => '流程实例'],
            ['name' => 'task:view', 'display_name' => '查看任务', 'group' => '任务管理'],
            ['name' => 'task:approve', 'display_name' => '审批任务', 'group' => '任务管理'],
            ['name' => 'task:reject', 'display_name' => '驳回任务', 'group' => '任务管理'],
            ['name' => 'task:transfer', 'display_name' => '转交任务', 'group' => '任务管理'],
        ];

        foreach ($permissions as $perm) {
            Permission::create($perm);
        }

        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => '超级管理员',
            'description' => '拥有所有权限',
        ]);

        $adminRole->permissions()->sync(Permission::all());

        $userRole = Role::create([
            'name' => 'user',
            'display_name' => '普通用户',
            'description' => '基础用户权限',
        ]);

        $userRole->permissions()->sync([17, 18, 19, 20, 21]);

        $admin = User::create([
            'username' => 'admin',
            'name' => '系统管理员',
            'email' => 'admin@example.com',
            'phone' => '13800138000',
            'department' => '信息技术部',
            'position' => '系统管理员',
            'password' => Hash::make('admin123'),
            'status' => 1,
        ]);

        $admin->roles()->sync([$adminRole->id]);

        $user1 = User::create([
            'username' => 'zhangsan',
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'phone' => '13800138001',
            'department' => '市场部',
            'position' => '市场经理',
            'password' => Hash::make('123456'),
            'status' => 1,
        ]);

        $user1->roles()->sync([$userRole->id]);

        $user2 = User::create([
            'username' => 'lisi',
            'name' => '李四',
            'email' => 'lisi@example.com',
            'phone' => '13800138002',
            'department' => '财务部',
            'position' => '财务主管',
            'password' => Hash::make('123456'),
            'status' => 1,
        ]);

        $user2->roles()->sync([$userRole->id]);

        $user3 = User::create([
            'username' => 'wangwu',
            'name' => '王五',
            'email' => 'wangwu@example.com',
            'phone' => '13800138003',
            'department' => '总经理办公室',
            'position' => '总经理',
            'password' => Hash::make('123456'),
            'status' => 1,
        ]);

        $user3->roles()->sync([$userRole->id]);
    }
}
