<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAppSettingRequest;
use App\Models\AppSetting;
use App\Services\AppBrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingController extends Controller
{
    public function index(AppBrandingService $brandingService): Response
    {
        $settings = AppSetting::current();

        return Inertia::render('AppSettings/Index', [
            'settings' => [
                'app_name' => $settings->app_name,
                'tagline' => $settings->tagline,
                'logo_url' => $brandingService->logoUrl($settings),
                'brand_color_400' => $settings->brand_color_400,
                'brand_color_500' => $settings->brand_color_500,
                'brand_color_600' => $settings->brand_color_600,
                'brand_color_700' => $settings->brand_color_700,
            ],
            'defaults' => [
                'app_name' => config('app.name', 'HRM'),
                'tagline' => 'Attendance',
                ...$brandingService->defaultColors(),
            ],
        ]);
    }

    public function update(UpdateAppSettingRequest $request, AppBrandingService $brandingService): RedirectResponse
    {
        $settings = AppSetting::current();

        if ($request->boolean('remove_logo') && $settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $settings->logo_path = $request->file('logo')->store('app-settings', 'public');
        }

        $settings->fill([
            'app_name' => $request->string('app_name')->toString(),
            'tagline' => $request->input('tagline'),
            ...$request->colorValues(),
        ]);

        $settings->save();

        return back()->with('success', 'App settings updated successfully.');
    }
}
