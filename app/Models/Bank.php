<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'name',
    'sort_order',
    'is_active',
])]
class Bank extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Bank>  $query
     * @return Builder<Bank>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Bank>  $query
     * @return Builder<Bank>
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
            ->get(['code', 'name'])
            ->map(fn (self $bank) => [
                'value' => $bank->code,
                'label' => $bank->name,
            ])
            ->all();
    }

    public static function labelFor(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return static::query()->where('code', $code)->value('name') ?? $code;
    }

    public static function defaultCode(): ?string
    {
        return static::query()->active()->ordered()->value('code');
    }

    public function isInUse(): bool
    {
        if (\App\Models\Employee::query()->where('bank_name', $this->code)->exists()) {
            return true;
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('grade_payroll_component')
            && \Illuminate\Support\Facades\DB::table('grade_payroll_component')->where('loan_bank', $this->code)->exists()) {
            return true;
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('designation_payroll_component')
            && \Illuminate\Support\Facades\DB::table('designation_payroll_component')->where('loan_bank', $this->code)->exists()) {
            return true;
        }

        return false;
    }
}
