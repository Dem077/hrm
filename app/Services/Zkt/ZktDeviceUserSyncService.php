<?php

namespace App\Services\Zkt;

use App\Enums\ZktDevicePrivilege;
use App\Enums\ZktDeviceUserSyncStatus;
use App\Models\Employee;
use App\Models\ZktDevice;
use App\Models\ZktDeviceEmployeeSync;
use App\Models\ZktLocationGroup;
use Illuminate\Support\Collection;
use Throwable;

class ZktDeviceUserSyncService
{
    public function __construct(
        protected ZktDeviceClient $client,
    ) {}

    /**
     * @return list<array{device_id: int, device_name: string, status: string, message: string|null}>
     */
    public function syncEmployee(Employee $employee): array
    {
        if (! $employee->is_active) {
            return $this->removeEmployeeFromAllDevices($employee);
        }

        $targetDeviceIds = $this->targetDeviceIdsForEmployee($employee);
        $currentDeviceIds = $employee->zktDeviceSyncs()
            ->where('sync_status', '!=', ZktDeviceUserSyncStatus::Removed->value)
            ->pluck('zkt_device_id')
            ->all();

        $results = [];

        foreach ($targetDeviceIds as $deviceId) {
            $device = ZktDevice::query()->find($deviceId);

            if (! $device || ! $device->is_active || ! $device->isManagedDevice()) {
                continue;
            }

            $results[] = $this->syncEmployeeToDevice($employee, $device);
        }

        $removeDeviceIds = array_diff($currentDeviceIds, $targetDeviceIds);

        foreach ($removeDeviceIds as $deviceId) {
            $device = ZktDevice::query()->find($deviceId);

            if (! $device || ! $device->isManagedDevice()) {
                continue;
            }

            $results[] = $this->removeEmployeeFromDevice($employee, $device);
        }

        return $results;
    }

    /**
     * @return list<array{device_id: int, device_name: string, status: string, message: string|null}>
     */
    public function removeEmployeeFromAllDevices(Employee $employee): array
    {
        $results = [];

        foreach ($employee->zktDeviceSyncs as $sync) {
            $device = $sync->device;

            if (! $device || ! $device->isManagedDevice()) {
                continue;
            }

            if ($sync->sync_status === ZktDeviceUserSyncStatus::Removed) {
                continue;
            }

            $results[] = $this->removeEmployeeFromDevice($employee, $device);
        }

        return $results;
    }

    /**
     * @return list<array{employee_id: int, employee_name: string, status: string, message: string|null}>
     */
    public function syncLocationGroup(ZktLocationGroup $group): array
    {
        $results = [];
        $group = $group->fresh();

        foreach (Employee::query()->whereIn('id', $group->eligibleEmployeeIds())->where('is_active', true)->get() as $employee) {
            foreach ($this->syncEmployee($employee) as $deviceResult) {
                $results[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    ...$deviceResult,
                ];
            }
        }

        return $results;
    }

    /**
     * @return list<array{employee_id: int, employee_name: string, status: string, message: string|null}>
     */
    public function syncDeviceUsers(ZktDevice $device): array
    {
        if (! $device->is_active || ! $device->isManagedDevice()) {
            return [];
        }

        $employeeIds = $this->employeeIdsForDevice($device);
        $results = [];

        foreach (Employee::query()->whereIn('id', $employeeIds)->where('is_active', true)->get() as $employee) {
            $result = $this->syncEmployeeToDevice($employee, $device);
            $results[] = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                ...$result,
            ];
        }

        $syncedEmployeeIds = $employeeIds;
        $staleSyncs = $device->employeeSyncs()
            ->with('employee:id,name')
            ->whereNotIn('employee_id', $syncedEmployeeIds)
            ->where('sync_status', '!=', ZktDeviceUserSyncStatus::Removed->value)
            ->get();

        foreach ($staleSyncs as $sync) {
            if (! $sync->employee) {
                continue;
            }

            $result = $this->removeEmployeeFromDevice($sync->employee, $device);
            $results[] = [
                'employee_id' => $sync->employee_id,
                'employee_name' => $sync->employee->name,
                ...$result,
            ];
        }

        return $results;
    }

    public function syncEmployeeLocationGroups(Employee $employee, array $locationGroupIds): void
    {
        $employee->zktLocationGroups()->sync($locationGroupIds);
        $this->syncEmployee($employee->fresh(['zktLocationGroups', 'zktDeviceSyncs.device']));
    }

    /**
     * @return array{status: string, device_id?: int, device_name?: string, message: string, updated_fields?: list<string>}
     */
    public function pullEmployeeCredentialsFromDevices(Employee $employee): array
    {
        $lastError = null;

        foreach ($this->devicesToSearchForEmployee($employee) as $device) {
            if (! $device->isManagedDevice()) {
                continue;
            }

            try {
                $users = $this->client->fetchUsers($device);
                $deviceUser = $this->findDeviceUser($users, $employee->staff_id);

                if ($deviceUser === null) {
                    continue;
                }

                $updatedFields = $this->applyDeviceUserToEmployee($employee, $deviceUser);

                if ($updatedFields === []) {
                    return [
                        'status' => 'unchanged',
                        'device_id' => $device->id,
                        'device_name' => $device->name,
                        'message' => "Found {$employee->name} on {$device->name}, but no card number, password, or privilege was available to import.",
                        'updated_fields' => [],
                    ];
                }

                return [
                    'status' => 'updated',
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'message' => 'Imported '.implode(', ', $updatedFields)." from {$device->name}.",
                    'updated_fields' => $updatedFields,
                ];
            } catch (Throwable $exception) {
                $lastError = $exception->getMessage();
            }
        }

        if ($lastError !== null) {
            return [
                'status' => 'failed',
                'message' => $lastError,
            ];
        }

        return [
            'status' => 'not_found',
            'message' => 'This employee was not found on any available machine.',
        ];
    }

    /**
     * @return list<array{employee_id: int, employee_name: string, staff_id: string, status: string, updated_fields: list<string>}>
     */
    public function pullDeviceCredentials(ZktDevice $device): array
    {
        if (! $device->is_active || ! $device->isManagedDevice()) {
            return [];
        }

        $users = $this->client->fetchUsers($device);
        $results = [];

        foreach ($users as $deviceUser) {
            $staffId = trim((string) ($deviceUser['user_id'] ?? ''));

            if ($staffId === '') {
                continue;
            }

            $employee = Employee::query()->where('staff_id', $staffId)->first();

            if (! $employee) {
                continue;
            }

            $updatedFields = $this->applyDeviceUserToEmployee($employee, $deviceUser);

            $results[] = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'staff_id' => $employee->staff_id,
                'status' => $updatedFields === [] ? 'unchanged' : 'updated',
                'updated_fields' => $updatedFields,
            ];
        }

        return $results;
    }

    /**
     * @return array{device_id: int, device_name: string, status: string, message: string|null}
     */
    protected function syncEmployeeToDevice(Employee $employee, ZktDevice $device): array
    {
        try {
            $users = $this->client->fetchUsers($device);
            $existing = $this->findDeviceUser($users, $employee->staff_id);
            $uid = $existing !== null
                ? (int) $existing['uid']
                : $this->resolveNextUid($users, $employee);
            $cardNo = $this->resolveDeviceCardNumber($employee, $existing);
            $password = $this->resolveDevicePassword($employee, $existing);

            $this->client->pushUser(
                $device,
                $uid,
                $employee->staff_id,
                $employee->name,
                $employee->device_privilege->deviceRole(),
                $cardNo,
                $password,
            );

            ZktDeviceEmployeeSync::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'zkt_device_id' => $device->id,
                ],
                [
                    'device_uid' => $uid,
                    'sync_status' => ZktDeviceUserSyncStatus::Synced,
                    'last_synced_at' => now(),
                    'last_error' => null,
                ],
            );

            return [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'status' => ZktDeviceUserSyncStatus::Synced->value,
                'message' => null,
            ];
        } catch (Throwable $exception) {
            ZktDeviceEmployeeSync::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'zkt_device_id' => $device->id,
                ],
                [
                    'sync_status' => ZktDeviceUserSyncStatus::Failed,
                    'last_error' => $exception->getMessage(),
                ],
            );

            return [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'status' => ZktDeviceUserSyncStatus::Failed->value,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{device_id: int, device_name: string, status: string, message: string|null}
     */
    protected function removeEmployeeFromDevice(Employee $employee, ZktDevice $device): array
    {
        $sync = ZktDeviceEmployeeSync::query()
            ->where('employee_id', $employee->id)
            ->where('zkt_device_id', $device->id)
            ->first();

        try {
            $users = $this->client->fetchUsers($device);
            $existing = $this->findDeviceUser($users, $employee->staff_id);

            if ($existing !== null) {
                $this->client->removeUserByUid($device, (int) $existing['uid']);
            }

            if ($sync) {
                $sync->update([
                    'sync_status' => ZktDeviceUserSyncStatus::Removed,
                    'last_synced_at' => now(),
                    'last_error' => null,
                ]);
            }

            return [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'status' => ZktDeviceUserSyncStatus::Removed->value,
                'message' => null,
            ];
        } catch (Throwable $exception) {
            if ($sync) {
                $sync->update([
                    'sync_status' => ZktDeviceUserSyncStatus::Failed,
                    'last_error' => $exception->getMessage(),
                ]);
            }

            return [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'status' => ZktDeviceUserSyncStatus::Failed->value,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return list<int>
     */
    protected function targetDeviceIdsForEmployee(Employee $employee): array
    {
        return ZktLocationGroup::activeGroupsForEmployee($employee)
            ->flatMap(fn (ZktLocationGroup $group) => $group->devices->pluck('id'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function employeeIdsForDevice(ZktDevice $device): array
    {
        return ZktLocationGroup::query()
            ->where('is_active', true)
            ->whereHas('devices', fn ($query) => $query->where('zkt_devices.id', $device->id))
            ->get()
            ->flatMap(fn (ZktLocationGroup $group) => $group->eligibleEmployeeIds())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $existing
     */
    protected function resolveDeviceCardNumber(Employee $employee, ?array $existing): int
    {
        if (filled($employee->device_card_number)) {
            return (int) $employee->device_card_number;
        }

        if ($existing !== null) {
            return (int) trim((string) ($existing['card_no'] ?? '0'));
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>|null  $existing
     */
    protected function resolveDevicePassword(Employee $employee, ?array $existing): string
    {
        if (filled($employee->device_password)) {
            return (string) $employee->device_password;
        }

        if ($existing !== null) {
            return trim((string) ($existing['password'] ?? ''));
        }

        return '';
    }

    /**
     * @return Collection<int, ZktDevice>
     */
    protected function devicesToSearchForEmployee(Employee $employee): Collection
    {
        $groupDeviceIds = ZktLocationGroup::activeGroupsForEmployee($employee)
            ->flatMap(fn (ZktLocationGroup $group) => $group->devices->pluck('id'))
            ->unique()
            ->values();

        return ZktDevice::query()
            ->where('is_active', true)
            ->where('ip_address', '!=', '0.0.0.0')
            ->when(
                $groupDeviceIds->isNotEmpty(),
                fn ($query) => $query->whereIn('id', $groupDeviceIds),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $deviceUser
     * @return list<string>
     */
    protected function applyDeviceUserToEmployee(Employee $employee, array $deviceUser): array
    {
        $updates = [];
        $changed = [];

        $cardNumber = $this->normalizeDeviceCardNumber($deviceUser);
        if ($cardNumber !== null) {
            $updates['device_card_number'] = $cardNumber;
            $changed[] = 'card number';
        }

        $password = trim((string) ($deviceUser['password'] ?? ''));
        if ($password !== '') {
            $updates['device_password'] = substr($password, 0, 8);
            $changed[] = 'password';
        }

        $privilege = ZktDevicePrivilege::tryFromDeviceRole((int) ($deviceUser['role'] ?? 0));
        if ($privilege !== null) {
            $updates['device_privilege'] = $privilege;
            $changed[] = 'privilege';
        }

        if ($updates !== []) {
            $employee->update($updates);
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $deviceUser
     */
    protected function normalizeDeviceCardNumber(array $deviceUser): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) ($deviceUser['card_no'] ?? '')));

        if ($digits === '' || preg_match('/^0+$/', $digits)) {
            return null;
        }

        return $digits;
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @return array<string, mixed>|null
     */
    protected function findDeviceUser(array $users, string $staffId): ?array
    {
        foreach ($users as $user) {
            $deviceUserId = trim((string) ($user['user_id'] ?? ''));

            if ($deviceUserId !== '' && $deviceUserId === trim($staffId)) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     */
    protected function resolveNextUid(array $users, Employee $employee): int
    {
        $storedUid = $employee->zktDeviceSyncs()
            ->whereNotNull('device_uid')
            ->value('device_uid');

        if ($storedUid) {
            return (int) $storedUid;
        }

        $usedUids = collect($users)
            ->pluck('uid')
            ->filter()
            ->map(fn ($uid) => (int) $uid);

        $maxUid = $usedUids->max() ?? 0;

        for ($uid = 1; $uid <= 65535; $uid++) {
            if (! $usedUids->contains($uid)) {
                return $uid;
            }
        }

        throw new ZktDeviceException('No available user UID slots on the device.');
    }
}
