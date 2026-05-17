<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Services\WorkflowEngine;

class WorkflowInstanceController extends Controller
{
    protected $workflowEngine;

    public function __construct(WorkflowEngine $workflowEngine)
    {
        $this->workflowEngine = $workflowEngine;
    }

    public function index(Request $request)
    {
        $query = WorkflowInstance::with('workflow', 'starter', 'currentNode')->orderBy('id', 'desc');

        if ($request->has('workflow_id')) {
            $query->where('workflow_id', $request->workflow_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('started_by')) {
            $query->where('started_by', $request->started_by);
        }

        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where('title', 'like', "%{$keyword}%");
        }

        $instances = $query->paginate($request->get('pageSize', 20));

        return response()->json([
            'code' => 0,
            'data' => $instances,
        ]);
    }

    public function myInstances(Request $request)
    {
        $query = WorkflowInstance::with('workflow', 'starter', 'currentNode')
            ->where('started_by', auth()->id())
            ->orderBy('id', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $instances = $query->paginate($request->get('pageSize', 20));

        return response()->json([
            'code' => 0,
            'data' => $instances,
        ]);
    }

    public function show($id)
    {
        $instance = WorkflowInstance::with([
            'workflow',
            'starter',
            'tasks.assignee',
            'logs.operator',
        ])->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $instance,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'workflow_id' => 'required|exists:workflows,id',
            'title' => 'required|string|max:200',
            'variables' => 'array',
            'business_type' => 'nullable|string|max:100',
            'business_id' => 'nullable|string|max:50',
        ]);

        try {
            $instance = $this->workflowEngine->startWorkflow(
                $validated['workflow_id'],
                $validated['title'],
                $validated['variables'] ?? [],
                $validated['business_type'] ?? null,
                $validated['business_id'] ?? null
            );

            return response()->json([
                'code' => 0,
                'message' => '流程启动成功',
                'data' => $instance,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function cancel($id)
    {
        try {
            $instance = $this->workflowEngine->cancelInstance($id);

            return response()->json([
                'code' => 0,
                'message' => '流程已取消',
                'data' => $instance,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getFlowChart($id)
    {
        $instance = WorkflowInstance::with('workflow.nodes', 'workflow.edges')->findOrFail($id);

        $nodes = $instance->workflow->nodes->map(function ($node) use ($instance) {
            $status = 'pending';

            $completedTasks = $instance->tasks->where('node_id', $node->node_id)->whereIn('status', [
                WorkflowTask::STATUS_APPROVED,
                WorkflowTask::STATUS_REJECTED,
            ]);

            if ($completedTasks->count() > 0) {
                $status = $completedTasks->first()->status == WorkflowTask::STATUS_APPROVED ? 'approved' : 'rejected';
            }

            if ($instance->current_node_id == $node->id) {
                $status = 'current';
            }

            return array_merge($node->toArray(), ['flow_status' => $status]);
        });

        return response()->json([
            'code' => 0,
            'data' => [
                'instance' => $instance,
                'nodes' => $nodes,
                'edges' => $instance->workflow->edges,
            ],
        ]);
    }
}
