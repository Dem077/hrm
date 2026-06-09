<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'code',
    'description',
    'requires_document',
    'is_visible_to_employees',
    'is_active',
    'sort_order',
    'annual_limit',
])]
class LeaveType extends Model
{
    protected function casts(): array
    {
        return [
            'requires_document' => 'boolean',
            'is_visible_to_employees' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'annual_limit' => 'integer',
        ];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'requires_document' => $this->requires_document,
            'is_visible_to_employees' => $this->is_visible_to_employees,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'annual_limit' => $this->annual_limit,
            'leave_requests_count' => $this->leave_requests_count ?? $this->leaveRequests()->count(),
        ];
    }
}
