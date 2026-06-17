<?php

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['app-settings.view', 'app-settings.update'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['app-settings.view', 'app-settings.update']);

    Storage::fake('public');
});

it('shows the app settings page', function () {
    $this->actingAs($this->user)
        ->get('/app-settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('AppSettings/Index'));
});

it('updates app name and brand colors', function () {
    $response = $this->actingAs($this->user)->post('/app-settings', [
        'app_name' => 'Acme HRM',
        'tagline' => 'People Ops',
        'brand_color_400' => '#93c5fd',
        'brand_color_500' => '#60a5fa',
        'brand_color_600' => '#3b82f6',
        'brand_color_700' => '#2563eb',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $settings = AppSetting::current();

    expect($settings->app_name)->toBe('Acme HRM')
        ->and($settings->tagline)->toBe('People Ops')
        ->and($settings->brand_color_600)->toBe('#3b82f6');
});

it('uploads and removes the app logo', function () {
    $logo = UploadedFile::fake()->image('logo.png', 120, 120);

    $this->actingAs($this->user)->post('/app-settings', [
        'app_name' => 'Acme HRM',
        'tagline' => 'People Ops',
        'logo' => $logo,
        'brand_color_400' => '#fbbf24',
        'brand_color_500' => '#f59e0b',
        'brand_color_600' => '#d97706',
        'brand_color_700' => '#b45309',
    ])->assertRedirect();

    $settings = AppSetting::current()->fresh();

    expect($settings->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($settings->logo_path);

    $this->actingAs($this->user)->post('/app-settings', [
        'app_name' => 'Acme HRM',
        'tagline' => 'People Ops',
        'remove_logo' => true,
        'brand_color_400' => '#fbbf24',
        'brand_color_500' => '#f59e0b',
        'brand_color_600' => '#d97706',
        'brand_color_700' => '#b45309',
    ])->assertRedirect();

    $settings->refresh();

    expect($settings->logo_path)->toBeNull();
});

it('shares branding with inertia pages', function () {
    AppSetting::current()->update([
        'app_name' => 'Shared Brand',
        'brand_color_600' => '#3b82f6',
    ]);

    $this->actingAs($this->user)
        ->get('/app-settings')
        ->assertInertia(fn ($page) => $page
            ->where('branding.app_name', 'Shared Brand')
            ->where('branding.brand_color_600', '#3b82f6'));
});
