<?php

namespace App\Models;

use App\Enums\ZktAdmsCommandStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'zkt_device_id',
    'command_no',
    'payload',
    'status',
    'result',
    'sent_at',
    'completed_at',
])]
class ZktAdmsCommand extends Model
{
    protected function casts(): array
    {
        return [
            'command_no' => 'integer',
            'status' => ZktAdmsCommandStatus::class,
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZktDevice::class, 'zkt_device_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        return [
            'id' => $this->id,
            'command_no' => $this->command_no,
            'payload' => $this->payload,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'result' => $this->result,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
