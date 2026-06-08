<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'date',
    'notes',
])]
class PublicHoliday extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => $this->date?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
