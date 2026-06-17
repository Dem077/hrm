<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

class AppBrandingService
{
    /**
     * @return array<string, string>
     */
    public function defaultColors(): array
    {
        return [
            'brand_color_400' => '#fbbf24',
            'brand_color_500' => '#f59e0b',
            'brand_color_600' => '#d97706',
            'brand_color_700' => '#b45309',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentation(?AppSetting $settings = null): array
    {
        $settings ??= AppSetting::current();

        return [
            'app_name' => $settings->app_name,
            'tagline' => $settings->tagline,
            'logo_url' => $this->logoUrl($settings),
            'brand_color_400' => $settings->brand_color_400,
            'brand_color_500' => $settings->brand_color_500,
            'brand_color_600' => $settings->brand_color_600,
            'brand_color_700' => $settings->brand_color_700,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function cssVariables(?AppSetting $settings = null): array
    {
        $branding = $this->presentation($settings);

        return [
            '--color-brand-400' => $branding['brand_color_400'],
            '--color-brand-500' => $branding['brand_color_500'],
            '--color-brand-600' => $branding['brand_color_600'],
            '--color-brand-700' => $branding['brand_color_700'],
        ];
    }

    public function logoUrl(?AppSetting $settings = null): ?string
    {
        $settings ??= AppSetting::current();

        if (! $settings->logo_path) {
            return null;
        }

        if (! Storage::disk('public')->exists($settings->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($settings->logo_path);
    }

    public function cssVariablesInline(?AppSetting $settings = null): string
    {
        return collect($this->cssVariables($settings))
            ->map(fn (string $value, string $key) => "{$key}: {$value};")
            ->implode(' ');
    }
}
