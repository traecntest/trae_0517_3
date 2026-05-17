<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTask extends Model
{
    protected $fillable = [
        'instance_id',
        'node_id',
        'node_name',
        'assignee_id',
        'assignee_type',
        'status',
        'comment',
        'created_by',
        'claimed_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;
    const STATUS_TRANSFERRED = 3;
    const STATUS_REVOKED = 4;

    public function instance()
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending()
    {
        return $this->status == self::STATUS_PENDING;
    }

    public function isCompleted()
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED]);
    }
}
