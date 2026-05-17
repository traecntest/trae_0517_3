<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowNode extends Model
{
    protected $fillable = [
        'workflow_id',
        'node_id',
        'name',
        'type',
        'config',
        'x',
        'y',
        'width',
        'height',
    ];

    protected $casts = [
        'config' => 'json',
        'x' => 'integer',
        'y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    const TYPE_START = 'start';
    const TYPE_END = 'end';
    const TYPE_APPROVAL = 'approval';
    const TYPE_CONDITION = 'condition';
    const TYPE_PARALLEL = 'parallel';
    const TYPE_SUBPROCESS = 'subprocess';
    const TYPE_AUTOMATION = 'automation';
    const TYPE_DELAY = 'delay';

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function incomingEdges()
    {
        return $this->hasMany(WorkflowEdge::class, 'target_node_id', 'node_id')
            ->where('workflow_id', $this->workflow_id);
    }

    public function outgoingEdges()
    {
        return $this->hasMany(WorkflowEdge::class, 'source_node_id', 'node_id')
            ->where('workflow_id', $this->workflow_id);
    }
}
