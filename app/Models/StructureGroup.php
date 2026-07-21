<?php

namespace App\Models;

use App\Enums\StructureGroupCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'sort_order',
])]
class StructureGroup extends Model
{
    protected function casts(): array
    {
        return [
            'code' => StructureGroupCode::class,
            'sort_order' => 'integer',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(StructureNode::class)->whereNull('parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function allNodes(): HasMany
    {
        return $this->hasMany(StructureNode::class)->orderBy('sort_order')->orderBy('name');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(StructureLevel::class)->orderBy('sort_order')->orderBy('level_number');
    }

    public function allowsNodes(): bool
    {
        return $this->code->allowsNodes();
    }
}
