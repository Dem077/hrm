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
            $device->update([
                'connection_status' => ZktConnectionStatus::Online,
                'last_connected_at' => now(),
                'last_sync_error' => null,
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

            $device->update([
                'connection_status' => ZktConnectionStatus::Online,
                'last_connected_at' => now(),
            ]);

            return $records;
        } finally {
            $zk->disconnect();
        }
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
