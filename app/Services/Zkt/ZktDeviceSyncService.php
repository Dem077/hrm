<?php

namespace App\Services\Zkt;

use App\Enums\ZktSyncStatus;
use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use App\Models\ZktDeviceSyncLog;
use Carbon\Carbon;
use Throwable;

class ZktDeviceSyncService
{
    public function __construct(
        protected ZktDeviceClient $client,
    ) {}

    public function sync(ZktDevice $device): ZktDeviceSyncLog
    {
        $syncLog = ZktDeviceSyncLog::query()->create([
            'zkt_device_id' => $device->id,
            'status' => ZktSyncStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $records = $this->client->fetchAttendance($device);
            $stored = $this->storeAttendance($device, $records);

            $syncLog->update([
                'status' => ZktSyncStatus::Success,
                'records_fetched' => count($records),
                'records_stored' => $stored,
                'message' => "Synced {$stored} new attendance record(s).",
                'completed_at' => now(),
            ]);

            $device->update([
                'last_synced_at' => now(),
                'last_sync_error' => null,
            ]);
        } catch (Throwable $exception) {
            $syncLog->update([
                'status' => ZktSyncStatus::Failed,
                'message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $device->update([
                'last_sync_error' => $exception->getMessage(),
            ]);
        }

        return $syncLog->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function storeAttendance(ZktDevice $device, array $records): int
    {
        $stored = 0;

        foreach ($records as $record) {
            $punchedAt = Carbon::parse($record['record_time']);

            $log = ZktAttendanceLog::withTrashed()->firstOrCreate(
                [
                    'zkt_device_id' => $device->id,
                    'device_uid' => (int) $record['uid'],
                    'punched_at' => $punchedAt,
                ],
                [
                    'device_user_id' => (string) $record['user_id'],
                    'punch_state' => (int) ($record['state'] ?? 0),
                    'punch_type' => isset($record['type']) ? (int) $record['type'] : null,
                ],
            );

            $wasRestored = false;

            if ($log->trashed()) {
                $log->restore();
                $log->forceFill([
                    'device_user_id' => (string) $record['user_id'],
                    'punch_state' => (int) ($record['state'] ?? 0),
                    'punch_type' => isset($record['type']) ? (int) $record['type'] : null,
                    'removal_reason' => null,
                    'removed_by_user_id' => null,
                ])->save();
                $wasRestored = true;
            }

            if ($log->wasRecentlyCreated || $wasRestored) {
                $stored++;
            }
        }

        return $stored;
    }
}
