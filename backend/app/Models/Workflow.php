<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'category',
        'icon',
        'color',
        'type',
        'status',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => 'integer',
        'type' => 'integer',
        'version' => 'integer',
    ];

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_DISABLED = 2;

    const TYPE_APPROVAL = 1;
    const TYPE_BUSINESS = 2;
    const TYPE_AUTOMATION = 3;

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versions()
    {
        return $this->hasMany(WorkflowVersion::class)->orderBy('version', 'desc');
    }

    public function activeVersion()
    {
        return $this->hasOne(WorkflowVersion::class)->where('is_active', 1);
    }

    public function nodes()
    {
        return $this->hasMany(WorkflowNode::class);
    }

    public function edges()
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    public function instances()
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function isDraft()
    {
        return $this->status == self::STATUS_DRAFT;
    }

    public function isPublished()
    {
        return $this->status == self::STATUS_PUBLISHED;
    }

    public function isDisabled()
    {
        return $this->status == self::STATUS_DISABLED;
    }
}
