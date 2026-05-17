<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Models\WorkflowInstanceLog;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use Illuminate\Support\Facades\DB;

class WorkflowEngine
{
    public function startWorkflow($workflowId, $title, $variables = [], $businessType = null, $businessId = null)
    {
        $workflow = Workflow::with('nodes', 'edges')->findOrFail($workflowId);

        if (!$workflow->isPublished()) {
            throw new \Exception('流程未发布，无法启动');
        }

        return DB::transaction(function () use ($workflow, $title, $variables, $businessType, $businessId) {
            $instance = WorkflowInstance::create([
                'workflow_id' => $workflow->id,
                'workflow_version' => $workflow->version,
                'business_type' => $businessType,
                'business_id' => $businessId,
                'title' => $title,
                'status' => WorkflowInstance::STATUS_RUNNING,
                'started_by' => auth()->id(),
                'started_at' => now(),
                'variables' => $variables,
            ]);

            $startNode = $workflow->nodes->where('type', WorkflowNode::TYPE_START)->first();

            $this->logAction($instance->id, $startNode->node_id, 'start', '流程启动');

            $nextNodeId = $this->getNextNode($workflow, $startNode->node_id, $variables);

            if ($nextNodeId) {
                $this->processNode($instance, $nextNodeId, $variables);
            }

            return $instance->load('tasks', 'logs');
        });
    }

    public function approveTask($taskId, $comment = null, $variables = [])
    {
        $task = WorkflowTask::findOrFail($taskId);

        if ($task->assignee_id != auth()->id()) {
            throw new \Exception('无权限处理此任务');
        }

        if (!$task->isPending()) {
            throw new \Exception('任务已处理');
        }

        return DB::transaction(function () use ($task, $comment, $variables) {
            $task->update([
                'status' => WorkflowTask::STATUS_APPROVED,
                'comment' => $comment,
                'completed_at' => now(),
            ]);

            $instance = $task->instance;

            $this->logAction($instance->id, $task->node_id, 'approve', $comment, $variables);

            if (!empty($variables)) {
                $instance->variables = array_merge((array)$instance->variables, $variables);
                $instance->save();
            }

            $workflow = Workflow::with('nodes', 'edges')->find($instance->workflow_id);

            $nextNodeId = $this->getNextNode($workflow, $task->node_id, $instance->variables);

            if ($nextNodeId) {
                $this->processNode($instance, $nextNodeId, $instance->variables);
            } else {
                $this->completeInstance($instance);
            }

            return $task->fresh();
        });
    }

    public function rejectTask($taskId, $comment = null)
    {
        $task = WorkflowTask::findOrFail($taskId);

        if ($task->assignee_id != auth()->id()) {
            throw new \Exception('无权限处理此任务');
        }

        if (!$task->isPending()) {
            throw new \Exception('任务已处理');
        }

        return DB::transaction(function () use ($task, $comment) {
            $task->update([
                'status' => WorkflowTask::STATUS_REJECTED,
                'comment' => $comment,
                'completed_at' => now(),
            ]);

            $instance = $task->instance;

            $this->logAction($instance->id, $task->node_id, 'reject', $comment);

            $instance->update([
                'status' => WorkflowInstance::STATUS_REJECTED,
                'ended_at' => now(),
            ]);

            return $task->fresh();
        });
    }

    protected function processNode($instance, $nodeId, $variables = [])
    {
        $workflow = Workflow::with('nodes', 'edges')->find($instance->workflow_id);
        $node = $workflow->nodes->where('node_id', $nodeId)->first();

        if (!$node) {
            throw new \Exception("节点不存在: {$nodeId}");
        }

        $instance->update(['current_node_id' => $node->id]);

        switch ($node->type) {
            case WorkflowNode::TYPE_END:
                $this->completeInstance($instance);
                break;

            case WorkflowNode::TYPE_APPROVAL:
                $this->createApprovalTask($instance, $node);
                break;

            case WorkflowNode::TYPE_CONDITION:
                $nextNodeId = $this->evaluateCondition($workflow, $node, $variables);
                if ($nextNodeId) {
                    $this->processNode($instance, $nextNodeId, $variables);
                }
                break;

            case WorkflowNode::TYPE_PARALLEL:
                $this->processParallel($instance, $workflow, $node, $variables);
                break;

            case WorkflowNode::TYPE_AUTOMATION:
                $this->executeAutomation($instance, $node, $variables);
                $nextNodeId = $this->getNextNode($workflow, $nodeId, $variables);
                if ($nextNodeId) {
                    $this->processNode($instance, $nextNodeId, $variables);
                } else {
                    $this->completeInstance($instance);
                }
                break;

            default:
                $nextNodeId = $this->getNextNode($workflow, $nodeId, $variables);
                if ($nextNodeId) {
                    $this->processNode($instance, $nextNodeId, $variables);
                }
                break;
        }
    }

    protected function createApprovalTask($instance, $node)
    {
        $config = $node->config;
        $assigneeId = $config['assignee_id'] ?? null;

        if (!$assigneeId) {
            throw new \Exception("审批节点未设置处理人: {$node->name}");
        }

        WorkflowTask::create([
            'instance_id' => $instance->id,
            'node_id' => $node->node_id,
            'node_name' => $node->name,
            'assignee_id' => $assigneeId,
            'assignee_type' => 'user',
            'status' => WorkflowTask::STATUS_PENDING,
            'created_by' => auth()->id(),
        ]);

        $this->logAction($instance->id, $node->node_id, 'create_task', "创建审批任务: {$node->name}");
    }

    protected function evaluateCondition($workflow, $node, $variables)
    {
        $outgoingEdges = $workflow->edges->where('source_node_id', $node->node_id);

        foreach ($outgoingEdges as $edge) {
            $condition = $edge->condition;

            if (!$condition || $this->evaluateExpression($condition, $variables)) {
                return $edge->target_node_id;
            }
        }

        return null;
    }

    protected function evaluateExpression($condition, $variables)
    {
        if (!isset($condition['expression'])) {
            return true;
        }

        $expression = $condition['expression'];

        foreach ($variables as $key => $value) {
            $expression = str_replace('{' . $key . '}', json_encode($value), $expression);
        }

        try {
            return eval("return ({$expression});");
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function processParallel($instance, $workflow, $node, $variables)
    {
        $outgoingEdges = $workflow->edges->where('source_node_id', $node->node_id);

        foreach ($outgoingEdges as $edge) {
            $this->processNode($instance, $edge->target_node_id, $variables);
        }
    }

    protected function executeAutomation($instance, $node, $variables)
    {
        $config = $node->config;
        $action = $config['action'] ?? null;

        $this->logAction($instance->id, $node->node_id, 'automation', "执行自动化操作: {$action}", [
            'action' => $action,
            'config' => $config,
        ]);

        return true;
    }

    protected function getNextNode($workflow, $currentNodeId, $variables)
    {
        $outgoingEdges = $workflow->edges->where('source_node_id', $currentNodeId);

        if ($outgoingEdges->count() === 0) {
            return null;
        }

        if ($outgoingEdges->count() === 1) {
            return $outgoingEdges->first()->target_node_id;
        }

        foreach ($outgoingEdges as $edge) {
            $condition = $edge->condition;
            if (!$condition || $this->evaluateExpression($condition, $variables)) {
                return $edge->target_node_id;
            }
        }

        return null;
    }

    protected function completeInstance($instance)
    {
        $instance->update([
            'status' => WorkflowInstance::STATUS_COMPLETED,
            'ended_at' => now(),
            'current_node_id' => null,
        ]);

        $this->logAction($instance->id, null, 'complete', '流程完成');
    }

    protected function logAction($instanceId, $nodeId, $action, $comment = null, $extra = [])
    {
        WorkflowInstanceLog::create([
            'instance_id' => $instanceId,
            'node_id' => $nodeId,
            'action' => $action,
            'comment' => $comment,
            'operator_id' => auth()->id(),
            'extra' => $extra,
        ]);
    }

    public function cancelInstance($instanceId)
    {
        $instance = WorkflowInstance::findOrFail($instanceId);

        if (!$instance->isRunning()) {
            throw new \Exception('只能取消运行中的流程');
        }

        return DB::transaction(function () use ($instance) {
            $instance->update([
                'status' => WorkflowInstance::STATUS_CANCELLED,
                'ended_at' => now(),
            ]);

            $instance->tasks()
                ->where('status', WorkflowTask::STATUS_PENDING)
                ->update(['status' => WorkflowTask::STATUS_REVOKED]);

            $this->logAction($instance->id, null, 'cancel', '流程已取消');

            return $instance->fresh();
        });
    }
}
