<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'code',
    'description',
    'sort_order',
    'is_active',
])]
class Designation extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function payrollComponents(): BelongsToMany
    {
        return $this->belongsToMany(PayrollComponent::class, 'designation_payroll_component')
            ->withPivot(['amount', 'loan_months', 'loan_bank'])
            ->withTimestamps()
            ->orderBy('payroll_components.sort_order')
            ->orderBy('payroll_components.name');
    }
}
