<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'app_name',
        'tagline',
        'logo_path',
        'leave_carry_forward_enabled',
        'brand_color_400',
        'brand_color_500',
        'brand_color_600',
        'brand_color_700',
    ];

    protected function casts(): array
    {
        return [
            'leave_carry_forward_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'app_name' => config('app.name', 'HRM'),
            'tagline' => 'Attendance',
            'leave_carry_forward_enabled' => true,
            'brand_color_400' => '#fbbf24',
            'brand_color_500' => '#f59e0b',
            'brand_color_600' => '#d97706',
            'brand_color_700' => '#b45309',
        ]);
    }
}
