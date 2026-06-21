<?php

namespace App\Services\Zkt;

use App\Enums\ZktConnectionStatus;
use App\Models\ZktDevice;
use Mithun\PhpZkteco\Libs\ZKTeco;
use Throwable;

class ZktDeviceClient
{
    public function makeConnection(ZktDevice $device): ZKTeco
    {
        $tcpmux = [];

        if ($device->tcpmux_enabled && $device->tcpmux_subdomain && $device->tcpmux_port) {
            $tcpmux = [
                'subdomain' => $device->tcpmux_subdomain,
                'port' => $device->tcpmux_port,
            ];
        }

        $protocol = $device->protocol instanceof \BackedEnum
            ? $device->protocol->value
            : (string) $device->protocol;

        return new ZKTeco(
            host: $device->ip_address,
            port: (int) $device->port,
            shouldPing: false,
            timeout: (int) config('zkt.connection_timeout', 25),
            password: (int) $device->comm_password,
            protocol: $protocol,
            tcpmux: $tcpmux,
        );
    }

    /**
     * @return array{connected: bool, message: string, device_info: array<string, string|null>}
     */
    public function probe(ZktDevice $device): array
    {
        try {
            $zk = $this->makeConnection($device);

            if (! $zk->connect()) {
                return [
                    'connected' => false,
                    'message' => 'Unable to establish connection to the device.',
                    'device_info' => [],
                ];
            }

            $deviceInfo = [
                'serial_number' => $this->safeCall(fn () => $zk->serialNumber()),
                'model_name' => $this->safeCall(fn () => $zk->deviceName()),
                'firmware_version' => $this->safeCall(fn () => $zk->version()),
            ];

            $zk->disconnect();

            return [
                'connected' => true,
                'message' => 'Successfully connected to the device.',
                'device_info' => $deviceInfo,
            ];
        } catch (Throwable $exception) {
            return [
                'connected' => false,
                'message' => $exception->getMessage(),
                'device_info' => [],
            ];
        }
    }

    /**
     * @return array{connected: bool, message: string, device_info: array<string, string|null>}
     */
    public function testConnection(ZktDevice $device): array
    {
        $result = $this->probe($device);

        if (! $device->exists) {
            return $result;
        }

        if ($result['connected']) {
            $this->markOnline($device, [
                'serial_number' => $result['device_info']['serial_number'] ?: $device->serial_number,
                'model_name' => $result['device_info']['model_name'] ?: $device->model_name,
                'firmware_version' => $result['device_info']['firmware_version'] ?: $device->firmware_version,
            ]);
        } else {
            $this->markOffline($device, $result['message']);
        }

        return $result;
    }

    /**
     * @return array{device_time: string|null, server_time: string}
     */
    public function readDeviceTime(ZktDevice $device): array
    {
        $zk = $this->makeConnection($device);

        if (! $zk->connect()) {
            throw new ZktDeviceException('Unable to connect to the device.');
        }

        try {
            $deviceTime = $zk->getTime();

            if ($deviceTime === false) {
                throw new ZktDeviceException('Unable to read the device clock.');
            }

            $this->markOnline($device);

            return [
                'device_time' => is_string($deviceTime) ? $deviceTime : null,
                'server_time' => now()->format('Y-m-d H:i:s'),
            ];
        } finally {
            $zk->disconnect();
        }
    }

    /**
     * @return array{
     *     device_time_before: string|null,
     *     device_time_after: string|null,
     *     server_time: string
     * }
     */
    public function syncDeviceTime(ZktDevice $device): array
    {
        $zk = $this->makeConnection($device);

        if (! $zk->connect()) {
            throw new ZktDeviceException('Unable to connect to the device.');
        }

        try {
            $deviceTimeBefore = $zk->getTime();

            if ($deviceTimeBefore === false) {
                throw new ZktDeviceException('Unable to read the device clock before syncing.');
            }

            $serverTime = now()->format('Y-m-d H:i:s');
            $result = $zk->setTime($serverTime);

            if ($result === false) {
                throw new ZktDeviceException('Device rejected the time sync command.');
            }

            $deviceTimeAfter = $zk->getTime();

            if ($deviceTimeAfter === false) {
                throw new ZktDeviceException('Time was sent but could not be verified on the device.');
            }

            $this->markOnline($device);

            return [
                'device_time_before' => is_string($deviceTimeBefore) ? $deviceTimeBefore : null,
                'device_time_after' => is_string($deviceTimeAfter) ? $deviceTimeAfter : null,
                'server_time' => $serverTime,
            ];
        } finally {
            $zk->disconnect();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchUsers(ZktDevice $device): array
    {
        $zk = $this->makeConnection($device);

        if (! $zk->connect()) {
            throw new ZktDeviceException('Unable to connect to the device.');
        }

        try {
            $users = $zk->getUsers();

            if ($users === false) {
                throw new ZktDeviceException('Device returned invalid user data.');
            }

            $this->markOnline($device);

            return $users;
        } finally {
            $zk->disconnect();
        }
    }

    /**
     * @return array{uid: int, user_id: string}
     */
    public function pushUser(
        ZktDevice $device,
        int $uid,
        string $userId,
        string $name,
        int $role = 0,
        int $cardNo = 0,
        string $password = '',
    ): array {
        $zk = $this->makeConnection($device);

        if (! $zk->connect()) {
            throw new ZktDeviceException('Unable to connect to the device.');
        }

        try {
            $userId = substr($userId, 0, 9);
            $name = substr($name, 0, 24);

            $result = $zk->setUser($uid, $userId, $name, $password, $role, $cardNo);

            if ($result === false) {
                throw new ZktDeviceException('Device rejected the user profile update.');
            }

            $this->markOnline($device);

            return [
                'uid' => $uid,
                'user_id' => $userId,
            ];
        } finally {
            $zk->disconnect();
        }
    }

    public function removeUserByUid(ZktDevice $device, int $uid): void
    {
        $zk = $this->makeConnection($device);

        if (! $zk->connect()) {
            throw new ZktDeviceException('Unable to connect to the device.');
        }

        try {
            $result = $zk->removeUser($uid);

            if ($result === false) {
                throw new ZktDeviceException('Device rejected the user removal command.');
            }

            $this->markOnline($device);
        } finally {
            $zk->disconnect();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAttendance(ZktDevice $device): array
    {
        $zk = $this->makeConnection($device);

        if (! $zk->connect()) {
            throw new ZktDeviceException('Unable to connect to the device.');
        }

        try {
            $records = $zk->getAttendances(
                maxRetries: (int) config('zkt.attendance_max_retries', 3),
            );

            if ($records === false) {
                throw new ZktDeviceException('Device returned corrupted attendance data.');
            }

            $this->markOnline($device);

            return $records;
        } finally {
            $zk->disconnect();
        }
    }

    protected function markOnline(ZktDevice $device, array $attributes = []): void
    {
        if (! $device->is_active) {
            return;
        }

        $device->update(array_merge([
            'connection_status' => ZktConnectionStatus::Online,
            'last_connected_at' => now(),
            'last_sync_error' => null,
        ], $attributes));
    }

    protected function markOffline(ZktDevice $device, string $message): void
    {
        $device->update([
            'connection_status' => ZktConnectionStatus::Offline,
            'last_sync_error' => $message,
        ]);
    }

    protected function safeCall(callable $callback): ?string
    {
        try {
            $result = $callback();

            return is_string($result) && $result !== '' ? $result : null;
        } catch (Throwable) {
            return null;
        }
    }
}
