<?php

namespace App\Models;

use App\Enums\LeaveApprovalStepKey;
use App\Enums\LeaveApprovalStepStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'overtime_request_id',
    'step_order',
    'step_key',
    'label',
    'approver_employee_id',
    'status',
    'acted_by_employee_id',
    'acted_at',
    'notes',
])]
class OvertimeRequestApprovalStep extends Model
{
    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'step_key' => LeaveApprovalStepKey::class,
            'status' => LeaveApprovalStepStatus::class,
            'acted_at' => 'datetime',
        ];
    }

    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'acted_by_employee_id');
    }
}
