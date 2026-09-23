<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'code',
    'description',
    'is_global',
    'is_active',
    'sort_order',
    'definition',
    'created_by_user_id',
])]
class ReportTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'definition' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Templates visible to a user with reports.view:
     * global templates, plus any they created themselves.
     *
     * @param  Builder<ReportTemplate>  $query
     * @return Builder<ReportTemplate>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('is_global', true)
                ->orWhere('created_by_user_id', $user->id);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_global' => $this->is_global,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'definition' => $this->definition ?? [],
            'created_by_user_id' => $this->created_by_user_id,
            'created_by' => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
