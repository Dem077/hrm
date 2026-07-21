<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_run_id',
    'event_type',
    'performed_by_user_id',
    'ip_address',
    'user_agent',
    'password_confirmed',
    'context',
])]
class PayrollRunAuditLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'password_confirmed' => 'boolean',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
