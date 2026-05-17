<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\WorkflowController;
use App\Http\Controllers\Api\WorkflowInstanceController;
use App\Http\Controllers\Api\WorkflowTaskController;

Route::group([
    'prefix' => 'auth',
], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::get('userinfo', [AuthController::class, 'userInfo'])->middleware('auth:api');
});

Route::group([
    'middleware' => 'auth:api',
], function () {

    Route::put('users/password', [UserController::class, 'updatePassword']);
    Route::get('users/options', [UserController::class, 'options']);
    Route::apiResource('users', UserController::class);

    Route::get('roles/all', [RoleController::class, 'all']);
    Route::apiResource('roles', RoleController::class);

    Route::get('permissions/all', [PermissionController::class, 'all']);
    Route::apiResource('permissions', PermissionController::class);

    Route::post('workflows/{id}/design', [WorkflowController::class, 'saveDesign']);
    Route::post('workflows/{id}/publish', [WorkflowController::class, 'publish']);
    Route::post('workflows/{id}/disable', [WorkflowController::class, 'disable']);
    Route::post('workflows/{id}/enable', [WorkflowController::class, 'enable']);
    Route::get('workflows/{id}/definition', [WorkflowController::class, 'getDefinition']);
    Route::get('workflows/options', [WorkflowController::class, 'options']);
    Route::apiResource('workflows', WorkflowController::class);

    Route::get('workflow-instances/my', [WorkflowInstanceController::class, 'myInstances']);
    Route::post('workflow-instances/{id}/cancel', [WorkflowInstanceController::class, 'cancel']);
    Route::get('workflow-instances/{id}/flowchart', [WorkflowInstanceController::class, 'getFlowChart']);
    Route::apiResource('workflow-instances', WorkflowInstanceController::class);

    Route::get('workflow-tasks/my', [WorkflowTaskController::class, 'myTasks']);
    Route::get('workflow-tasks/pending', [WorkflowTaskController::class, 'pendingTasks']);
    Route::get('workflow-tasks/completed', [WorkflowTaskController::class, 'completedTasks']);
    Route::post('workflow-tasks/{id}/approve', [WorkflowTaskController::class, 'approve']);
    Route::post('workflow-tasks/{id}/reject', [WorkflowTaskController::class, 'reject']);
    Route::post('workflow-tasks/{id}/claim', [WorkflowTaskController::class, 'claim']);
    Route::post('workflow-tasks/{id}/transfer', [WorkflowTaskController::class, 'transfer']);
    Route::apiResource('workflow-tasks', WorkflowTaskController::class);
});
