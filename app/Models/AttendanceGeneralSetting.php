<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceGeneralSetting extends Model
{
    protected $fillable = [
        'payroll_period_start_day',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payroll_period_start_day' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'payroll_period_start_day' => 25,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        $startDay = $this->payroll_period_start_day;

        return [
            'payroll_period_start_day' => $startDay,
            'payroll_period_end_day' => $startDay > 1 ? $startDay - 1 : null,
        ];
    }
}
