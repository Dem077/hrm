<?php

namespace App\Services\Adms;

use App\Models\Employee;
use App\Models\ZktDevice;

class AdmsUserCommandBuilder
{
    public function buildUserCommand(Employee $employee, int $accessGroup = 1): string
    {
        $privilege = $employee->device_privilege?->deviceRole() ?? 0;
        $accessGroup = max(1, min($accessGroup, 99));

        // Classic Push / F18: DATA UPDATE USERINFO … with Pri= (not DATA USER / Privilege=).
        $fields = [
            'PIN='.$employee->staff_id,
            'Name='.$this->sanitizeName($employee->name),
            'Pri='.$privilege,
            'Grp='.$accessGroup,
        ];

        if (filled($employee->device_card_number)) {
            $fields[] = 'Card='.$employee->device_card_number;
        }

        if (filled($employee->device_password)) {
            $fields[] = 'Passwd='.$employee->device_password;
        }

        return 'DATA UPDATE USERINFO '.implode("\t", $fields);
    }

    public function buildDeleteUserCommand(Employee $employee): string
    {
        return 'DATA DELETE USERINFO PIN='.$employee->staff_id;
    }

    public function buildQueryUsersCommand(): string
    {
        return 'DATA QUERY USERINFO';
    }

    public function buildClearLogCommand(): string
    {
        return 'CLEAR LOG';
    }

    /**
     * Push wall-clock sync options for classic F18.
     *
     * SET TIME / SET DATE return -1002 on this firmware. The display clock is
     * driven by the HTTP Date header (local wall time) after options reload.
     *
     * @return list<string>
     */
    public function buildTimeSyncOptionCommands(?\DateTimeInterface $at = null): array
    {
        $interval = max(1, (int) config('zkt.time_sync_interval_seconds', 60));

        return [
            // Keep timezone at 0 — clock face comes from HTTP Date (local-as-GMT).
            'SET OPTION TimeZone=0',
            'SET OPTION SyncTime='.$interval,
            // Force the device to re-fetch handshake options (and re-read Date).
            'RELOAD OPTIONS',
            'CHECK',
        ];
    }

    public function buildSyncTimeOptionCommand(): string
    {
        $interval = max(1, (int) config('zkt.time_sync_interval_seconds', 60));

        return 'SET OPTION SyncTime='.$interval;
    }

    /**
     * Ask the device to re-upload attendance logs for a date range.
     */
    public function buildQueryAttLogCommand(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): string
    {
        $from ??= now()->subDays(7)->startOfDay();
        $to ??= now()->endOfDay();

        return 'DATA QUERY ATTLOG StartTime='.$from->format('Y-m-d H:i:s')
            .' EndTime='.$to->format('Y-m-d H:i:s');
    }

    /**
     * @return list<string>
     */
    public function buildSyncCommandsForDevice(ZktDevice $device, iterable $employees, iterable $removeEmployees = []): array
    {
        $commands = [];
        $accessGroup = max(1, min((int) $device->default_access_group, 99));

        foreach ($employees as $employee) {
            if ($employee instanceof Employee && $employee->is_active) {
                $commands[] = $this->buildUserCommand($employee, $accessGroup);
            }
        }

        foreach ($removeEmployees as $employee) {
            if ($employee instanceof Employee) {
                $commands[] = $this->buildDeleteUserCommand($employee);
            }
        }

        return $commands;
    }

    protected function sanitizeName(string $name): string
    {
        return preg_replace('/[\t\r\n]+/', ' ', trim($name)) ?: 'User';
    }
}
