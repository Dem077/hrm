<?php

namespace App\Services\Attendance;

use App\Enums\AttendancePunchSource;
use App\Models\Employee;
use App\Models\SelfPunchSite;
use App\Models\User;
use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use App\Support\PublicIp;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SelfPunchService
{
    public function punch(
        User $user,
        int $siteId,
        int $punchState,
        float $latitude,
        float $longitude,
        ?float $accuracyMeters,
        ?string $requestIp,
        string $clientDeviceId,
        ?string $reportedPublicIp = null,
    ): ZktAttendanceLog {
        if (! in_array($punchState, [0, 1], true)) {
            throw ValidationException::withMessages([
                'punch_state' => 'Only check in or check out is allowed.',
            ]);
        }

        $employee = $user->employee;

        if (! $employee || ! $employee->is_active) {
            throw ValidationException::withMessages([
                'employee' => 'Your login is not linked to an active employee profile.',
            ]);
        }

        $site = SelfPunchSite::query()
            ->whereKey($siteId)
            ->where('is_active', true)
            ->whereHas('employees', fn ($query) => $query->where('employees.id', $employee->id))
            ->first();

        if (! $site) {
            throw ValidationException::withMessages([
                'self_punch_site_id' => 'You are not assigned to this mobile punch site.',
            ]);
        }

        $effectiveIp = PublicIp::resolve($requestIp, $reportedPublicIp);

        if (! $site->clientIpIsAllowed($effectiveIp)) {
            throw ValidationException::withMessages([
                'network' => 'You must be connected to the office Wi‑Fi.',
            ]);
        }

        if ($accuracyMeters !== null && $accuracyMeters > $site->max_accuracy_meters) {
            throw ValidationException::withMessages([
                'accuracy_meters' => 'GPS accuracy is too low ('.$accuracyMeters.'m). Move outdoors or wait for a better signal (max '.$site->max_accuracy_meters.'m).',
            ]);
        }

        if (! $site->containsCoordinates($latitude, $longitude, $accuracyMeters)) {
            $distance = (int) round($site->distanceMetersFrom($latitude, $longitude));

            throw ValidationException::withMessages([
                'location' => "You are about {$distance}m from {$site->name}. Move within {$site->radius_meters}m of the site to punch.",
            ]);
        }

        $this->assertCooldown($employee);
        $this->assertUniqueClientDevice($employee, $clientDeviceId);
        $this->assertUniqueLocalRequestIp($employee, $requestIp);

        $device = ZktDevice::selfPunchDevice();
        $deviceUid = (int) (ZktAttendanceLog::query()
            ->where('zkt_device_id', $device->id)
            ->max('device_uid') ?? 0) + 1;

        return ZktAttendanceLog::query()->create([
            'zkt_device_id' => $device->id,
            'device_uid' => $deviceUid,
            'device_user_id' => $employee->staff_id,
            'punch_state' => $punchState,
            'punch_type' => null,
            'punched_at' => now(),
            'source' => AttendancePunchSource::SelfApp,
            'self_punch_site_id' => $site->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_meters' => $accuracyMeters !== null ? (int) round($accuracyMeters) : null,
            'client_ip' => $effectiveIp,
            'request_ip' => $requestIp,
            'client_device_id' => $clientDeviceId,
            'added_by_user_id' => $user->id,
        ]);
    }

    protected function assertCooldown(Employee $employee): void
    {
        $latest = ZktAttendanceLog::query()
            ->where('device_user_id', $employee->staff_id)
            ->where('source', AttendancePunchSource::SelfApp->value)
            ->latest('punched_at')
            ->first();

        if ($latest && $latest->punched_at?->gt(now()->subMinutes(2))) {
            throw ValidationException::withMessages([
                'punch_state' => 'Please wait at least 2 minutes between mobile punches.',
            ]);
        }
    }

    protected function assertUniqueClientDevice(Employee $employee, string $clientDeviceId): void
    {
        $timezone = config('app.timezone', 'UTC');
        $start = Carbon::now($timezone)->startOfDay()->utc();
        $end = Carbon::now($timezone)->endOfDay()->utc();

        $usedByAnotherEmployee = ZktAttendanceLog::query()
            ->where('source', AttendancePunchSource::SelfApp->value)
            ->where('client_device_id', $clientDeviceId)
            ->where('device_user_id', '!=', $employee->staff_id)
            ->whereBetween('punched_at', [$start, $end])
            ->exists();

        if ($usedByAnotherEmployee) {
            throw ValidationException::withMessages([
                'device_id' => 'Another employee has already used mobile punch from this browser today. Each person must use their own phone or computer.',
            ]);
        }
    }

    protected function assertUniqueLocalRequestIp(Employee $employee, ?string $requestIp): void
    {
        if (! PublicIp::isPrivate($requestIp)) {
            return;
        }

        $timezone = config('app.timezone', 'UTC');
        $start = Carbon::now($timezone)->startOfDay()->utc();
        $end = Carbon::now($timezone)->endOfDay()->utc();

        $usedByAnotherEmployee = ZktAttendanceLog::query()
            ->where('source', AttendancePunchSource::SelfApp->value)
            ->where('request_ip', $requestIp)
            ->where('device_user_id', '!=', $employee->staff_id)
            ->whereBetween('punched_at', [$start, $end])
            ->exists();

        if ($usedByAnotherEmployee) {
            throw ValidationException::withMessages([
                'network' => 'Another employee has already used mobile punch from this device or network today. Each person must use their own phone or computer.',
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function todaysPunches(Employee $employee): array
    {
        $timezone = config('app.timezone', 'UTC');
        $start = Carbon::now($timezone)->startOfDay()->utc();
        $end = Carbon::now($timezone)->endOfDay()->utc();

        return ZktAttendanceLog::query()
            ->with('selfPunchSite:id,name')
            ->where('device_user_id', $employee->staff_id)
            ->where('source', AttendancePunchSource::SelfApp->value)
            ->whereBetween('punched_at', [$start, $end])
            ->orderByDesc('punched_at')
            ->get()
            ->map(fn (ZktAttendanceLog $log) => [
                'id' => $log->id,
                'punch_state' => $log->punch_state,
                'punch_state_label' => $log->punchStateLabel(),
                'punched_at' => $log->punched_at?->timezone($timezone)->format('H:i:s'),
                'site_name' => $log->selfPunchSite?->name,
            ])
            ->values()
            ->all();
    }
}
