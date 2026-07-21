<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'structure_group_id',
    'parent_id',
    'name',
    'code',
    'description',
    'head_employee_id',
    'is_active',
    'sort_order',
])]
class StructureNode extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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

    public function headEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
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
