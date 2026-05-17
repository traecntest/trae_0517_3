<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstance extends Model
{
    protected $fillable = [
        'workflow_id',
        'workflow_version',
        'business_type',
        'business_id',
        'title',
        'description',
        'status',
        'started_by',
        'started_at',
        'ended_at',
        'current_node_id',
        'variables',
    ];

    protected $casts = [
        'variables' => 'json',
        'status' => 'integer',
    ];

    const STATUS_RUNNING = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_REJECTED = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_REVOKED = 4;

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function currentNode()
    {
        return $this->belongsTo(WorkflowNode::class, 'current_node_id');
    }

    public function tasks()
    {
        return $this->hasMany(WorkflowTask::class);
    }

    public function logs()
    {
        return $this->hasMany(WorkflowInstanceLog::class)->orderBy('created_at', 'desc');
    }

    public function isRunning()
    {
        return $this->status == self::STATUS_RUNNING;
    }

    public function isCompleted()
    {
        return $this->status == self::STATUS_COMPLETED;
    }
}
