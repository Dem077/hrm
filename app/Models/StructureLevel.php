<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'structure_group_id',
    'structure_node_id',
    'level_number',
    'reference_title',
    'sort_order',
])]
class StructureLevel extends Model
{
    protected function casts(): array
    {
        return [
            'level_number' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StructureGroup::class, 'structure_group_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(StructureNode::class, 'structure_node_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(StructureGrade::class)->orderBy('sort_order')->orderBy('grade');
    }
}
