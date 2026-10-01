<?php

namespace App\Services\Reports;

use App\Events\ReportJobProgressUpdated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportJobProgress
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    private const TTL_SECONDS = 60 * 60 * 6;

    public function create(string $action, ?int $userId = null, array $meta = []): string
    {
        $jobId = (string) Str::uuid();

        $this->put($jobId, array_merge([
            'job_id' => $jobId,
            'action' => $action,
            'status' => self::STATUS_QUEUED,
            'user_id' => $userId,
            'total' => 0,
            'done' => 0,
            'percent' => 0,
            'message' => 'Queued…',
            'error' => null,
            'cancel_requested' => false,
            'download_ready' => false,
            'filename' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], $meta), broadcast: true);

        return $jobId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $jobId): ?array
    {
        $payload = Cache::get($this->key($jobId));

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $jobId, array $attributes): void
    {
        $current = $this->get($jobId) ?? [
            'job_id' => $jobId,
            'status' => self::STATUS_QUEUED,
            'total' => 0,
            'done' => 0,
            'percent' => 0,
            'cancel_requested' => false,
        ];

        $merged = array_merge($current, $attributes, [
            'updated_at' => now()->toIso8601String(),
        ]);

        $total = max(0, (int) ($merged['total'] ?? 0));
        $done = max(0, (int) ($merged['done'] ?? 0));
        $merged['percent'] = $total > 0 ? (int) min(100, round(($done / $total) * 100)) : (int) ($merged['percent'] ?? 0);

        $this->put($jobId, $merged, broadcast: true);
    }

    public function requestCancel(string $jobId): bool
    {
        $current = $this->get($jobId);

        if ($current === null) {
            return false;
        }

        if (in_array($current['status'] ?? null, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true)) {
            return false;
        }

        $this->update($jobId, [
            'cancel_requested' => true,
            'message' => 'Cancel requested…',
        ]);

        return true;
    }

    public function isCancelRequested(string $jobId): bool
    {
        $current = $this->get($jobId);

        return (bool) ($current['cancel_requested'] ?? false);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function storeRows(string $jobId, array $rows): void
    {
        Cache::put($this->rowsKey($jobId), $rows, self::TTL_SECONDS);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function getRows(string $jobId): ?array
    {
        $rows = Cache::get($this->rowsKey($jobId));

        return is_array($rows) ? $rows : null;
    }

    public function storeCsv(string $jobId, string $contents, string $filename): void
    {
        Storage::disk('local')->put($this->csvPath($jobId), $contents);
        $this->update($jobId, [
            'download_ready' => true,
            'filename' => $filename,
        ]);
    }

    public function csvAbsolutePath(string $jobId): ?string
    {
        $path = $this->csvPath($jobId);

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->path($path);
    }

    public function deleteArtifacts(string $jobId): void
    {
        Cache::forget($this->rowsKey($jobId));
        Storage::disk('local')->delete($this->csvPath($jobId));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function put(string $jobId, array $payload, bool $broadcast = false): void
    {
        Cache::put($this->key($jobId), $payload, self::TTL_SECONDS);

        if ($broadcast) {
            try {
                broadcast(new ReportJobProgressUpdated($payload));
            } catch (\Throwable) {
                // Progress cache remains the source of truth; polling still works.
            }
        }
    }

    protected function key(string $jobId): string
    {
        return "report-job:{$jobId}";
    }

    protected function rowsKey(string $jobId): string
    {
        return "report-job-rows:{$jobId}";
    }

    protected function csvPath(string $jobId): string
    {
        return "report-jobs/{$jobId}.csv";
    }
}
