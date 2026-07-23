<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'structure_group_id',
    'parent_id',
    'name',
    'code',
    'description',
    'is_active',
    'sort_order',
    'leave_approval_workflow',
    'overtime_approval_workflow',
    'leave_approval_template_id',
    'overtime_approval_template_id',
])]
class StructureNode extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'leave_approval_workflow' => 'array',
            'overtime_approval_workflow' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StructureGroup::class, 'structure_group_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function leaveApprovalTemplate(): BelongsTo
    {
        return $this->belongsTo(ApprovalTemplate::class, 'leave_approval_template_id');
    }

    public function overtimeApprovalTemplate(): BelongsTo
    {
        return $this->belongsTo(ApprovalTemplate::class, 'overtime_approval_template_id');
    }

    public function headGrades(): BelongsToMany
    {
        return $this->belongsToMany(StructureGrade::class, 'structure_node_head_grade')
            ->withTimestamps()
            ->orderBy('structure_grades.sort_order')
            ->orderBy('structure_grades.grade');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(StructureLevel::class)->orderBy('sort_order')->orderBy('level_number');
    }

    /**
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }
}
