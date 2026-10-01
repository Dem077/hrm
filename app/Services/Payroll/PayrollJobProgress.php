<?php

namespace App\Services\Payroll;

use App\Events\PayrollJobProgressUpdated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PayrollJobProgress
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    private const TTL_SECONDS = 60 * 60 * 6;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function create(string $action, ?int $payrollRunId = null, ?int $userId = null, array $meta = []): string
    {
        $jobId = (string) Str::uuid();

        $this->put($jobId, array_merge([
            'job_id' => $jobId,
            'action' => $action,
            'status' => self::STATUS_QUEUED,
            'payroll_run_id' => $payrollRunId,
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

    public function storeFile(string $jobId, string $absolutePath, string $filename): void
    {
        $contents = @file_get_contents($absolutePath);

        if ($contents === false) {
            throw new \RuntimeException('Export file was not created.');
        }

        Storage::disk('local')->put($this->filePath($jobId), $contents);
        @unlink($absolutePath);

        $this->update($jobId, [
            'download_ready' => true,
            'filename' => $filename,
        ]);
    }

    public function fileAbsolutePath(string $jobId): ?string
    {
        $path = $this->filePath($jobId);

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->path($path);
    }

    public function deleteArtifacts(string $jobId): void
    {
        Storage::disk('local')->delete($this->filePath($jobId));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function put(string $jobId, array $payload, bool $broadcast = false): void
    {
        Cache::put($this->key($jobId), $payload, self::TTL_SECONDS);

        if ($broadcast) {
            try {
                broadcast(new PayrollJobProgressUpdated($payload));
            } catch (\Throwable) {
                // Progress cache remains the source of truth; polling still works.
            }
        }
    }

    protected function key(string $jobId): string
    {
        return "payroll-job:{$jobId}";
    }

    protected function filePath(string $jobId): string
    {
        return "payroll-jobs/{$jobId}.xlsx";
    }
}
