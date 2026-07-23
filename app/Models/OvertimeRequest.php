<?php

namespace App\Models;

use App\Enums\OvertimeRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'record_number',
    'employee_id',
    'overtime_date',
    'start_time',
    'end_time',
    'hours',
    'reason',
    'status',
    'approver_employee_id',
    'manager_reviewed_by_employee_id',
    'manager_reviewed_at',
    'manager_review_notes',
    'reviewed_by_employee_id',
    'reviewed_at',
    'review_notes',
])]
class OvertimeRequest extends Model
{
    protected function casts(): array
    {
        return [
            'overtime_date' => 'date',
            'hours' => 'decimal:2',
            'status' => OvertimeRequestStatus::class,
            'manager_reviewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    public function managerReviewedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_reviewed_by_employee_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by_employee_id');
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(OvertimeRequestApprovalStep::class)->orderBy('step_order');
    }

    public function isPendingManagerApproval(): bool
    {
        return $this->status === OvertimeRequestStatus::Pending;
    }

    public function isPendingHrApproval(): bool
    {
        return $this->status === OvertimeRequestStatus::PendingHr;
    }

    public function isPending(): bool
    {
        return $this->isPendingManagerApproval() || $this->isPendingHrApproval();
    }
}
