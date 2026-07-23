<?php

namespace App\Models;

use App\Enums\ApprovalWorkflowKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalTemplate extends Model
{
    protected $fillable = [
        'kind',
        'name',
        'description',
        'steps',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ApprovalWorkflowKind::class,
            'steps' => 'array',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function leaveNodes(): HasMany
    {
        return $this->hasMany(StructureNode::class, 'leave_approval_template_id');
    }

    public function overtimeNodes(): HasMany
    {
        return $this->hasMany(StructureNode::class, 'overtime_approval_template_id');
    }

    public function isInUse(): bool
    {
        $settings = AppSetting::current();

        if ((int) $settings->leave_approval_template_id === (int) $this->id
            || (int) $settings->overtime_approval_template_id === (int) $this->id) {
            return true;
        }

        return $this->leaveNodes()->exists() || $this->overtimeNodes()->exists();
    }
}
