<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference_no',
    'period_from',
    'period_to',
    'period_label',
    'period_source',
    'status',
    'created_by_user_id',
    'processed_by_user_id',
    'processed_at',
    'finalised_by_user_id',
    'finalised_at',
    'reopened_by_user_id',
    'reopened_at',
    'finalised_version',
])]
class PayrollRun extends Model
{
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'status' => PayrollRunStatus::class,
            'processed_at' => 'datetime',
            'finalised_at' => 'datetime',
            'reopened_at' => 'datetime',
            'finalised_version' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollRunAdjustment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PayrollRunAuditLog::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function finalisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalised_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }
}
