<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'staff_id',
    'name',
    'national_id',
    'email',
    'mobile_number',
    'joined_date',
    'gender',
    'department_id',
    'user_id',
    'manager_id',
    'is_active',
    'works_saturday',
])]
class Employee extends Model
{
    protected function casts(): array
    {
        return [
            'joined_date' => 'date',
            'gender' => Gender::class,
            'is_active' => 'boolean',
            'works_saturday' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_employee_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(ZktAttendanceLog::class, 'device_user_id', 'staff_id');
    }
}
