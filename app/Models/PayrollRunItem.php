<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_run_id',
    'employee_id',
    'department_id',
    'department_name',
    'designation_name',
    'staff_id',
    'employee_name',
    'national_id',
    'bank_name',
    'account_name',
    'account_no',
    'days_attended',
    'hours_worked',
    'base_gross',
    'base_deductions',
    'base_net',
    'manual_additions',
    'manual_deductions',
    'gross',
    'deductions',
    'net',
    'details',
    'attendance_summary',
    'sort_order',
])]
class PayrollRunItem extends Model
{
    protected function casts(): array
    {
        return [
            'days_attended' => 'integer',
            'hours_worked' => 'float',
            'base_gross' => 'float',
            'base_deductions' => 'float',
            'base_net' => 'float',
            'manual_additions' => 'float',
            'manual_deductions' => 'float',
            'gross' => 'float',
            'deductions' => 'float',
            'net' => 'float',
            'details' => 'array',
            'attendance_summary' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
