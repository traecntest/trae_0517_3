<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    public function index(Request $request)
    {
        $query = Workflow::with('creator')->orderBy('id', 'desc');

        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $workflows = $query->paginate($request->get('pageSize', 20));

        return response()->json([
            'code' => 0,
            'data' => $workflows,
        ]);
    }

    public function show($id)
    {
        $workflow = Workflow::with(['creator', 'nodes', 'edges', 'versions'])->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $workflow,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|unique:workflows|max:50',
            'description' => 'nullable|string',
            'category' => 'string|max:50',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'type' => 'integer|in:1,2,3',
        ]);

        $workflow = DB::transaction(function () use ($validated, $request) {
            $workflow = Workflow::create([
                ...$validated,
                'created_by' => auth()->id(),
                'status' => Workflow::STATUS_DRAFT,
                'version' => 1,
            ]);

            $startNode = WorkflowNode::create([
                'workflow_id' => $workflow->id,
                'node_id' => 'start_1',
                'name' => '开始',
                'type' => WorkflowNode::TYPE_START,
                'x' => 200,
                'y' => 100,
            ]);

            $endNode = WorkflowNode::create([
                'workflow_id' => $workflow->id,
                'node_id' => 'end_1',
                'name' => '结束',
                'type' => WorkflowNode::TYPE_END,
                'x' => 200,
                'y' => 300,
            ]);

            WorkflowEdge::create([
                'workflow_id' => $workflow->id,
                'edge_id' => 'edge_1',
                'source_node_id' => 'start_1',
                'target_node_id' => 'end_1',
            ]);

            return $workflow;
        });

        return response()->json([
            'code' => 0,
            'message' => '创建成功',
            'data' => $workflow->load('nodes', 'edges'),
        ]);
    }

    public function update(Request $request, $id)
    {
        $workflow = Workflow::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:100',
            'code' => 'string|unique:workflows,code,' . $id . '|max:50',
            'description' => 'nullable|string',
            'category' => 'string|max:50',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'type' => 'integer|in:1,2,3',
            'status' => 'integer|in:0,1,2',
        ]);

        $workflow->update([
            ...$validated,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => $workflow,
        ]);
    }

    public function destroy($id)
    {
        $workflow = Workflow::findOrFail($id);

        if ($workflow->instances()->count() > 0) {
            return response()->json([
                'code' => 400,
                'message' => '该流程已有运行实例，无法删除',
            ], 400);
        }

        DB::transaction(function () use ($workflow) {
            $workflow->nodes()->delete();
            $workflow->edges()->delete();
            $workflow->versions()->delete();
            $workflow->delete();
        });

        return response()->json([
            'code' => 0,
            'message' => '删除成功',
        ]);
    }

    public function saveDesign(Request $request, $id)
    {
        $workflow = Workflow::findOrFail($id);

        if (!$workflow->isDraft()) {
            return response()->json([
                'code' => 400,
                'message' => '只能编辑草稿状态的流程',
            ], 400);
        }

        $validated = $request->validate([
            'nodes' => 'required|array',
            'edges' => 'required|array',
        ]);

        DB::transaction(function () use ($workflow, $validated) {
            $workflow->nodes()->delete();
            $workflow->edges()->delete();

            foreach ($validated['nodes'] as $node) {
                WorkflowNode::create([
                    'workflow_id' => $workflow->id,
                    'node_id' => $node['node_id'],
                    'name' => $node['name'],
                    'type' => $node['type'],
                    'config' => $node['config'] ?? null,
                    'x' => $node['x'] ?? 0,
                    'y' => $node['y'] ?? 0,
                    'width' => $node['width'] ?? 160,
                    'height' => $node['height'] ?? 60,
                ]);
            }

            foreach ($validated['edges'] as $edge) {
                WorkflowEdge::create([
                    'workflow_id' => $workflow->id,
                    'edge_id' => $edge['edge_id'],
                    'source_node_id' => $edge['source_node_id'],
                    'target_node_id' => $edge['target_node_id'],
                    'label' => $edge['label'] ?? null,
                    'condition' => $edge['condition'] ?? null,
                ]);
            }
        });

        return response()->json([
            'code' => 0,
            'message' => '保存成功',
            'data' => $workflow->load('nodes', 'edges'),
        ]);
    }

    public function publish(Request $request, $id)
    {
        $workflow = Workflow::findOrFail($id);

        $validated = $request->validate([
            'change_log' => 'nullable|string',
        ]);

        DB::transaction(function () use ($workflow, $validated) {
            $nodes = $workflow->nodes;
            $edges = $workflow->edges;

            if ($nodes->where('type', WorkflowNode::TYPE_START)->count() !== 1) {
                throw new \Exception('流程必须有且仅有一个开始节点');
            }

            if ($nodes->where('type', WorkflowNode::TYPE_END)->count() < 1) {
                throw new \Exception('流程至少需要一个结束节点');
            }

            $newVersion = $workflow->version + 1;

            WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'version' => $newVersion,
                'definition' => [
                    'nodes' => $nodes,
                    'edges' => $edges,
                ],
                'change_log' => $validated['change_log'] ?? null,
                'is_active' => 1,
                'created_by' => auth()->id(),
            ]);

            WorkflowVersion::where('workflow_id', $workflow->id)
                ->where('id', '<>', $workflow->versions()->max('id'))
                ->update(['is_active' => 0]);

            $workflow->update([
                'status' => Workflow::STATUS_PUBLISHED,
                'version' => $newVersion,
                'updated_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'code' => 0,
            'message' => '发布成功',
            'data' => $workflow->fresh(),
        ]);
    }

    public function disable($id)
    {
        $workflow = Workflow::findOrFail($id);
        $workflow->update(['status' => Workflow::STATUS_DISABLED]);

        return response()->json([
            'code' => 0,
            'message' => '已停用',
        ]);
    }

    public function enable($id)
    {
        $workflow = Workflow::findOrFail($id);
        $workflow->update(['status' => Workflow::STATUS_PUBLISHED]);

        return response()->json([
            'code' => 0,
            'message' => '已启用',
        ]);
    }

    public function getDefinition($id)
    {
        $workflow = Workflow::with('nodes', 'edges')->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => [
                'nodes' => $workflow->nodes,
                'edges' => $workflow->edges,
            ],
        ]);
    }

    public function options()
    {
        $workflows = Workflow::where('status', Workflow::STATUS_PUBLISHED)
            ->select('id', 'name', 'code', 'category', 'type')
            ->get();

        return response()->json([
            'code' => 0,
            'data' => $workflows,
        ]);
    }
}
