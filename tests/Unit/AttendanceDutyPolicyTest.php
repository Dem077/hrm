<?php

use App\Models\AttendanceDutyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('uses a temporary duty policy inside its date range', function () {
    AttendanceDutyPolicy::query()->create([
        'effective_from' => '2020-01-01',
        'effective_until' => null,
        'duty_start_time' => '09:00:00',
        'duty_end_time' => '18:00:00',
        'grace_minutes' => 15,
        'saturday_duty_start_time' => '09:00:00',
        'saturday_duty_end_time' => '14:00:00',
        'saturday_grace_minutes' => 15,
    ]);

    AttendanceDutyPolicy::query()->create([
        'name' => 'Ramadan',
        'effective_from' => '2026-06-01',
        'effective_until' => '2026-06-15',
        'duty_start_time' => '08:00:00',
        'duty_end_time' => '15:00:00',
        'grace_minutes' => 10,
        'saturday_duty_start_time' => '08:00:00',
        'saturday_duty_end_time' => '12:00:00',
        'saturday_grace_minutes' => 10,
    ]);

    $inside = AttendanceDutyPolicy::forDate(Carbon::parse('2026-06-08'));
    $outside = AttendanceDutyPolicy::forDate(Carbon::parse('2026-06-20'));

    expect($inside->name)->toBe('Ramadan')
        ->and($inside->duty_start_time)->toBe('08:00:00')
        ->and($outside->name)->toBeNull()
        ->and($outside->duty_start_time)->toBe('09:00:00');
});

it('prefers the latest temporary policy when periods overlap', function () {
    AttendanceDutyPolicy::query()->create([
        'effective_from' => '2020-01-01',
        'effective_until' => null,
        'duty_start_time' => '09:00:00',
        'duty_end_time' => '18:00:00',
        'grace_minutes' => 15,
        'saturday_duty_start_time' => '09:00:00',
        'saturday_duty_end_time' => '14:00:00',
        'saturday_grace_minutes' => 15,
    ]);

    AttendanceDutyPolicy::query()->create([
        'name' => 'Earlier override',
        'effective_from' => '2026-06-01',
        'effective_until' => '2026-06-30',
        'duty_start_time' => '08:00:00',
        'duty_end_time' => '15:00:00',
        'grace_minutes' => 10,
        'saturday_duty_start_time' => '08:00:00',
        'saturday_duty_end_time' => '12:00:00',
        'saturday_grace_minutes' => 10,
    ]);

    AttendanceDutyPolicy::query()->create([
        'name' => 'Later override',
        'effective_from' => '2026-06-10',
        'effective_until' => '2026-06-20',
        'duty_start_time' => '07:00:00',
        'duty_end_time' => '14:00:00',
        'grace_minutes' => 5,
        'saturday_duty_start_time' => '07:00:00',
        'saturday_duty_end_time' => '11:00:00',
        'saturday_grace_minutes' => 5,
    ]);

    $policy = AttendanceDutyPolicy::forDate(Carbon::parse('2026-06-15'));

    expect($policy->name)->toBe('Later override')
        ->and($policy->duty_start_time)->toBe('07:00:00');
});
