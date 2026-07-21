<?php

namespace App\Services\Adms;

use App\Models\Employee;
use App\Models\ZktDevice;

class AdmsUserCommandBuilder
{
    public function buildUserCommand(Employee $employee): string
    {
        $privilege = $employee->device_privilege?->deviceRole() ?? 0;

        $parts = [
            'DATA USER',
            'PIN='.$employee->staff_id,
            'Name='.$this->sanitizeName($employee->name),
            'Privilege='.$privilege,
        ];

        if (filled($employee->device_card_number)) {
            $parts[] = 'Card='.$employee->device_card_number;
        }

        if (filled($employee->device_password)) {
            $parts[] = 'Passwd='.$employee->device_password;
        }

        $parts[] = 'Grp=0';

        return implode("\t", $parts);
    }

    public function buildDeleteUserCommand(Employee $employee): string
    {
        return 'DATA DEL_USER PIN='.$employee->staff_id;
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
     * @return list<string>
     */
    public function buildSyncCommandsForDevice(ZktDevice $device, iterable $employees, iterable $removeEmployees = []): array
    {
        $commands = [];

        foreach ($employees as $employee) {
            if ($employee instanceof Employee && $employee->is_active) {
                $commands[] = $this->buildUserCommand($employee);
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
