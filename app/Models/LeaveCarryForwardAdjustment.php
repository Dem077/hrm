<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveCarryForwardAdjustment extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'from_period_start',
        'from_period_end',
        'to_period_start',
        'to_period_end',
        'days',
        'reason',
        'moved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'from_period_start' => 'date',
            'from_period_end' => 'date',
            'to_period_start' => 'date',
            'to_period_end' => 'date',
            'days' => 'integer',
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

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by_user_id');
    }
}
