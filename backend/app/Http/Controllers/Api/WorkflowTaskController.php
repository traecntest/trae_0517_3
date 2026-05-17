<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkflowTask;
use App\Services\WorkflowEngine;

class WorkflowTaskController extends Controller
{
    protected $workflowEngine;

    public function __construct(WorkflowEngine $workflowEngine)
    {
        $this->workflowEngine = $workflowEngine;
    }

    public function index(Request $request)
    {
        $query = WorkflowTask::with(['instance.workflow', 'assignee', 'creator'])->orderBy('id', 'desc');

        if ($request->has('assignee_id')) {
            $query->where('assignee_id', $request->assignee_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('instance_id')) {
            $query->where('instance_id', $request->instance_id);
        }

        $tasks = $query->paginate($request->get('pageSize', 20));

        return response()->json([
            'code' => 0,
            'data' => $tasks,
        ]);
    }

    public function myTasks(Request $request)
    {
        $query = WorkflowTask::with(['instance.workflow', 'creator'])
            ->where('assignee_id', auth()->id())
            ->orderBy('id', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->paginate($request->get('pageSize', 20));

        return response()->json([
            'code' => 0,
            'data' => $tasks,
        ]);
    }

    public function pendingTasks(Request $request)
    {
        $tasks = WorkflowTask::with(['instance.workflow', 'creator'])
            ->where('assignee_id', auth()->id())
            ->where('status', WorkflowTask::STATUS_PENDING)
            ->orderBy('id', 'desc')
            ->paginate($request->get('pageSize', 20));

        return response()->json([
            'code' => 0,
            'data' => $tasks,
        ]);
    }

    public function completedTasks(Request $request)
    {
        $tasks = WorkflowTask::with(['instance.workflow', 'creator'])
            ->where('assignee_id', auth()->id())
            ->whereIn('status', [WorkflowTask::STATUS_APPROVED, WorkflowTask::STATUS_REJECTED])
            ->orderBy('completed_at', 'desc')
            ->paginate($request->get('pageSize', 20));

        return response()->json([
            'code' => 0,
            'data' => $tasks,
        ]);
    }

    public function show($id)
    {
        $task = WorkflowTask::with(['instance.workflow', 'assignee', 'creator'])->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $task,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'comment' => 'nullable|string',
            'variables' => 'array',
        ]);

        try {
            $task = $this->workflowEngine->approveTask(
                $id,
                $validated['comment'] ?? null,
                $validated['variables'] ?? []
            );

            return response()->json([
                'code' => 0,
                'message' => '审批通过',
                'data' => $task,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'comment' => 'required|string',
        ]);

        try {
            $task = $this->workflowEngine->rejectTask($id, $validated['comment']);

            return response()->json([
                'code' => 0,
                'message' => '已驳回',
                'data' => $task,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function claim($id)
    {
        $task = WorkflowTask::findOrFail($id);

        if ($task->status != WorkflowTask::STATUS_PENDING) {
            return response()->json([
                'code' => 400,
                'message' => '任务状态不允许签收',
            ], 400);
        }

        $task->update([
            'assignee_id' => auth()->id(),
            'claimed_at' => now(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '签收成功',
            'data' => $task,
        ]);
    }

    public function transfer(Request $request, $id)
    {
        $task = WorkflowTask::findOrFail($id);

        if ($task->assignee_id != auth()->id()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限转交此任务',
            ], 403);
        }

        if (!$task->isPending()) {
            return response()->json([
                'code' => 400,
                'message' => '只能转交待处理的任务',
            ], 400);
        }

        $validated = $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'comment' => 'nullable|string',
        ]);

        $task->update([
            'assignee_id' => $validated['target_user_id'],
            'status' => WorkflowTask::STATUS_TRANSFERRED,
        ]);

        WorkflowTask::create([
            'instance_id' => $task->instance_id,
            'node_id' => $task->node_id,
            'node_name' => $task->node_name,
            'assignee_id' => $validated['target_user_id'],
            'assignee_type' => 'user',
            'status' => WorkflowTask::STATUS_PENDING,
            'created_by' => auth()->id(),
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '转交成功',
        ]);
    }
}
