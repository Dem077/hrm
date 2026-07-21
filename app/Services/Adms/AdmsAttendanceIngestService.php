<?php

namespace App\Services\Adms;

use App\Enums\AttendancePunchSource;
use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdmsAttendanceIngestService
{
    /**
     * @return array{stored: int, skipped: int}
     */
    public function ingestAttLog(ZktDevice $device, string $body): array
    {
        $stored = 0;
        $skipped = 0;

        $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $record = $this->parseAttLogLine($line);

                if ($record === null) {
                    $skipped++;

                    continue;
                }

                if ($this->storePunch($device, $record)) {
                    $stored++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $exception) {
                $skipped++;
                Log::warning('Failed to ingest ADMS ATTLOG line.', [
                    'device_id' => $device->id,
                    'line' => $line,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($stored > 0) {
            $device->update([
                'last_synced_at' => now(),
            ]);
        }

        return compact('stored', 'skipped');
    }

    /**
     * @return array{pin: string, punched_at: Carbon, state: int, type: int|null}|null
     */
    protected function parseAttLogLine(string $line): ?array
    {
        $parts = preg_split('/\t+/', $line) ?: [];

        if (count($parts) < 2) {
            $parts = preg_split('/\s+/', $line) ?: [];
        }

        if (count($parts) < 2) {
            return null;
        }

        $pin = trim((string) $parts[0]);
        $timeRaw = trim((string) $parts[1]);

        if ($pin === '' || $timeRaw === '') {
            return null;
        }

        $punchedAt = Carbon::parse($timeRaw);
        $state = isset($parts[2]) ? (int) $parts[2] : 0;
        $type = isset($parts[3]) ? (int) $parts[3] : null;

        return [
            'pin' => $pin,
            'punched_at' => $punchedAt,
            'state' => $state,
            'type' => $type,
        ];
    }

    /**
     * @param  array{pin: string, punched_at: Carbon, state: int, type: int|null}  $record
     */
    protected function storePunch(ZktDevice $device, array $record): bool
    {
        $deviceUid = $this->deriveDeviceUid($record['pin'], $record['punched_at'], $record['state']);

        $log = ZktAttendanceLog::withTrashed()->firstOrCreate(
            [
                'zkt_device_id' => $device->id,
                'device_uid' => $deviceUid,
                'punched_at' => $record['punched_at'],
            ],
            [
                'device_user_id' => $record['pin'],
                'punch_state' => $record['state'],
                'punch_type' => $record['type'],
                'source' => AttendancePunchSource::Device,
            ],
        );

        if ($log->trashed()) {
            $log->restore();
            $log->forceFill([
                'device_user_id' => $record['pin'],
                'punch_state' => $record['state'],
                'punch_type' => $record['type'],
                'source' => AttendancePunchSource::Device,
                'removal_reason' => null,
                'removed_by_user_id' => null,
            ])->save();

            return true;
        }

        return $log->wasRecentlyCreated;
    }

    protected function deriveDeviceUid(string $pin, Carbon $punchedAt, int $state): int
    {
        $hash = sprintf('%u', crc32($pin.'|'.$punchedAt->format('Y-m-d H:i:s').'|'.$state));

        return (int) ($hash % 2147483647) ?: 1;
    }
}
