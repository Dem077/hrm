<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'structure_level_id',
    'grade',
    'title',
    'sort_order',
    'is_active',
])]
class StructureGrade extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(StructureLevel::class, 'structure_level_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'grade_id');
    }

    public function payrollComponents(): BelongsToMany
    {
        return $this->belongsToMany(PayrollComponent::class, 'grade_payroll_component')
            ->withPivot(['amount', 'loan_months', 'loan_bank'])
            ->withTimestamps()
            ->orderBy('payroll_components.sort_order')
            ->orderBy('payroll_components.name');
    }

    public function label(): string
    {
        return "Grade {$this->grade} – {$this->title}";
    }

    /**
     * @return array{group: array{id: int, code: string, name: string}|null, node: array{id: int, name: string}|null, level: array{id: int, level_number: int, reference_title: string}|null, path_label: string}
     */
    public function resolvePath(): array
    {
        $this->loadMissing([
            'level.group',
            'level.node.group',
            'level.node.parent',
        ]);

        $level = $this->level;
        $group = $level?->group ?? $level?->node?->group;
        $node = $level?->node;

        $segments = [];

        if ($group) {
            $segments[] = $group->name;
        }

        if ($node) {
            $chain = [];
            $current = $node;
            while ($current) {
                array_unshift($chain, $current->name);
                $current = $current->parent;
            }
            $segments = array_merge($segments, $chain);
        }

        if ($level) {
            $segments[] = "Level {$level->level_number}";
            if ($level->reference_title) {
                $segments[count($segments) - 1] .= " ({$level->reference_title})";
            }
        }

        $segments[] = $this->label();

        return [
            'group' => $group ? [
                'id' => $group->id,
                'code' => $group->code->value,
                'name' => $group->name,
            ] : null,
            'node' => $node ? [
                'id' => $node->id,
                'name' => $node->name,
            ] : null,
            'level' => $level ? [
                'id' => $level->id,
                'level_number' => $level->level_number,
                'reference_title' => $level->reference_title,
            ] : null,
            'path_label' => implode(' › ', $segments),
        ];
    }
}
