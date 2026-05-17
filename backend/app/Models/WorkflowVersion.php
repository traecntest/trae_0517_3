<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowVersion extends Model
{
    protected $fillable = [
        'workflow_id',
        'version',
        'definition',
        'change_log',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'definition' => 'json',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
