<?php

namespace App\Support;

use App\Models\StructureNode;
use Illuminate\Support\Collection;

class StructureNodeOptions
{
    /**
     * @return list<array{id: int, name: string}>
     */
    public static function active(?int $onlyId = null): array
    {
        return StructureNode::query()
            ->with('group:id,name')
            ->when($onlyId, fn ($query) => $query->whereKey($onlyId))
            ->when(! $onlyId, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (StructureNode $node) => [
                'id' => $node->id,
                'name' => $node->group
                    ? "{$node->group->name} › {$node->name}"
                    : $node->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Employee>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Employee>
     */
    public static function constrainEmployeesByNode($query, ?int $nodeId)
    {
        if (! $nodeId) {
            return $query;
        }

        return $query->whereHas(
            'grade.level',
            fn ($levelQuery) => $levelQuery->where('structure_node_id', $nodeId),
        );
    }
}
