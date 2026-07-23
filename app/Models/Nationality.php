<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'sort_order',
    'is_active',
])]
class Nationality extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'name';
    }

    /**
     * @param  Builder<Nationality>  $query
     * @return Builder<Nationality>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Nationality>  $query
     * @return Builder<Nationality>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(bool $activeOnly = true): array
    {
        $query = static::query()->ordered();

        if ($activeOnly) {
            $query->active();
        }

        return $query
            ->get(['name'])
            ->map(fn (self $nationality) => [
                'value' => $nationality->name,
                'label' => $nationality->name,
            ])
            ->all();
    }

    public function isInUse(): bool
    {
        return Employee::query()->where('nationality', $this->name)->exists();
    }
}
