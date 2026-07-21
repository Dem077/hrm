<?php

namespace App\Services\Adms;

use App\Enums\ZktAdmsCommandStatus;
use App\Models\ZktAdmsCommand;
use App\Models\ZktDevice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdmsCommandQueue
{
    public function enqueue(ZktDevice $device, string $payload): ZktAdmsCommand
    {
        return DB::transaction(function () use ($device, $payload) {
            $nextNo = (int) ZktAdmsCommand::query()
                ->where('zkt_device_id', $device->id)
                ->lockForUpdate()
                ->max('command_no') + 1;

            return ZktAdmsCommand::query()->create([
                'zkt_device_id' => $device->id,
                'command_no' => max(1, $nextNo),
                'payload' => trim($payload),
                'status' => ZktAdmsCommandStatus::Pending,
            ]);
        });
    }

    /**
     * @return Collection<int, ZktAdmsCommand>
     */
    public function takePending(ZktDevice $device, int $limit = 20): Collection
    {
        $commands = ZktAdmsCommand::query()
            ->where('zkt_device_id', $device->id)
            ->where('status', ZktAdmsCommandStatus::Pending->value)
            ->orderBy('command_no')
            ->limit($limit)
            ->get();

        if ($commands->isEmpty()) {
            return $commands;
        }

        $ids = $commands->pluck('id')->all();

        ZktAdmsCommand::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => ZktAdmsCommandStatus::Sent->value,
                'sent_at' => now(),
            ]);

        return $commands->each(function (ZktAdmsCommand $command): void {
            $command->status = ZktAdmsCommandStatus::Sent;
            $command->sent_at = now();
        });
    }

    public function formatForDevice(Collection $commands): string
    {
        if ($commands->isEmpty()) {
            return "OK\r\n";
        }

        return $commands
            ->map(fn (ZktAdmsCommand $command) => "C:{$command->command_no}:{$command->payload}")
            ->implode("\r\n")."\r\n";
    }

    public function acknowledgeFromBody(ZktDevice $device, string $body): int
    {
        $acked = 0;
        $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! preg_match('/ID=(\d+)/i', $line, $matches)) {
                continue;
            }

            $commandNo = (int) $matches[1];
            $returnCode = null;

            if (preg_match('/Return=(-?\d+)/i', $line, $returnMatches)) {
                $returnCode = (int) $returnMatches[1];
            }

            $command = ZktAdmsCommand::query()
                ->where('zkt_device_id', $device->id)
                ->where('command_no', $commandNo)
                ->first();

            if (! $command) {
                continue;
            }

            $failed = $returnCode !== null && $returnCode !== 0;

            $command->update([
                'status' => $failed ? ZktAdmsCommandStatus::Failed : ZktAdmsCommandStatus::Done,
                'result' => $line,
                'completed_at' => now(),
            ]);

            $acked++;
        }

        return $acked;
    }

    public function pendingCount(ZktDevice $device): int
    {
        return ZktAdmsCommand::query()
            ->where('zkt_device_id', $device->id)
            ->whereIn('status', [
                ZktAdmsCommandStatus::Pending->value,
                ZktAdmsCommandStatus::Sent->value,
            ])
            ->count();
    }

    public function failedCount(ZktDevice $device): int
    {
        return ZktAdmsCommand::query()
            ->where('zkt_device_id', $device->id)
            ->where('status', ZktAdmsCommandStatus::Failed->value)
            ->count();
    }
}
