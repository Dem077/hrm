<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'app_name',
        'tagline',
        'logo_path',
        'brand_color_400',
        'brand_color_500',
        'brand_color_600',
        'brand_color_700',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'app_name' => config('app.name', 'HRM'),
            'tagline' => 'Attendance',
            'brand_color_400' => '#fbbf24',
            'brand_color_500' => '#f59e0b',
            'brand_color_600' => '#d97706',
            'brand_color_700' => '#b45309',
        ]);
    }
}
