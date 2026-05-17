<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowEdge extends Model
{
    protected $fillable = [
        'workflow_id',
        'edge_id',
        'source_node_id',
        'target_node_id',
        'label',
        'condition',
    ];

    protected $casts = [
        'condition' => 'json',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function sourceNode()
    {
        return $this->belongsTo(WorkflowNode::class, 'source_node_id', 'node_id')
            ->where('workflow_id', $this->workflow_id);
    }

    public function targetNode()
    {
        return $this->belongsTo(WorkflowNode::class, 'target_node_id', 'node_id')
            ->where('workflow_id', $this->workflow_id);
    }
}
