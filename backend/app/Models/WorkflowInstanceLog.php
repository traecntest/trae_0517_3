<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstanceLog extends Model
{
    protected $fillable = [
        'instance_id',
        'node_id',
        'action',
        'comment',
        'operator_id',
        'extra',
    ];

    protected $casts = [
        'extra' => 'json',
    ];

    public function instance()
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
