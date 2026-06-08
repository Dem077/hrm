<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'record_number',
    'employee_id',
    'leave_type_id',
    'start_date',
    'end_date',
    'days_count',
    'reason',
    'document_path',
    'status',
    'approver_employee_id',
    'manager_reviewed_by_employee_id',
    'manager_reviewed_at',
    'manager_review_notes',
    'reviewed_by_employee_id',
    'reviewed_at',
    'review_notes',
])]
class LeaveRequest extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days_count' => 'integer',
            'status' => LeaveRequestStatus::class,
            'manager_reviewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
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

    public function isPendingManagerApproval(): bool
    {
        return $this->status === LeaveRequestStatus::Pending;
    }

    public function isPendingHrApproval(): bool
    {
        return $this->status === LeaveRequestStatus::PendingHr;
    }

    public function isPending(): bool
    {
        return $this->isPendingManagerApproval() || $this->isPendingHrApproval();
    }
}
