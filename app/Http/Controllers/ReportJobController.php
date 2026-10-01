<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportJobProgress;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportJobController extends Controller
{
    public function status(string $jobId, ReportJobProgress $progress, Request $request)
    {
        $payload = $progress->get($jobId);

        if ($payload === null || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()?->id) {
            abort(404, 'Report job not found.');
        }

        return response()->json($payload);
    }

    public function cancel(string $jobId, ReportJobProgress $progress, Request $request)
    {
        $payload = $progress->get($jobId);

        if ($payload === null || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()?->id) {
            abort(404, 'Report job not found.');
        }

        $cancelled = $progress->requestCancel($jobId);

        return response()->json([
            'ok' => $cancelled,
            'job' => $progress->get($jobId),
        ]);
    }

    public function download(string $jobId, ReportJobProgress $progress, Request $request): BinaryFileResponse
    {
        $payload = $progress->get($jobId);

        if ($payload === null || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()?->id) {
            abort(404, 'Report job not found.');
        }

        if (($payload['status'] ?? null) !== ReportJobProgress::STATUS_COMPLETED || ! ($payload['download_ready'] ?? false)) {
            abort(409, 'Report file is not ready yet.');
        }

        $path = $progress->csvAbsolutePath($jobId);

        if ($path === null) {
            abort(404, 'Report file not found.');
        }

        $filename = (string) ($payload['filename'] ?? 'report.csv');

        return response()->download($path, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
