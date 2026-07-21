<?php

namespace App\Http\Controllers\Adms;

use App\Http\Controllers\Controller;
use App\Services\Adms\AdmsCdataService;
use App\Services\Adms\AdmsCommandQueue;
use App\Services\Adms\AdmsDeviceResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IclockController extends Controller
{
    public function __construct(
        protected AdmsDeviceResolver $deviceResolver,
        protected AdmsCdataService $cdataService,
        protected AdmsCommandQueue $commandQueue,
    ) {}

    public function cdata(Request $request): Response
    {
        $device = $this->resolveDevice($request);

        if (! $device) {
            return $this->plain("OK\r\n");
        }

        $this->deviceResolver->touch($device);

        if (strtolower((string) $request->query('type')) === 'time') {
            return $this->plain($this->cdataService->handleTimeSyncResponse());
        }

        if ($request->query('options') === 'all') {
            return $this->plain($this->cdataService->handleOptionsHandshake($device));
        }

        $table = $request->query('table') ?? $request->input('table');
        $body = $request->getContent();

        if ($body === '' && $request->filled('data')) {
            $body = (string) $request->input('data');
        }

        if ($request->isMethod('POST') || filled($table)) {
            return $this->plain(
                $this->cdataService->handleTable($device, is_string($table) ? $table : null, $body)
            );
        }

        return $this->plain($this->cdataService->handleOptionsHandshake($device));
    }

    public function getrequest(Request $request): Response
    {
        $device = $this->resolveDevice($request);

        if (! $device) {
            return $this->plain("OK\r\n");
        }

        $info = $request->query('INFO');
        $firmware = null;

        if (is_string($info) && $info !== '') {
            $parts = explode(',', $info);
            $firmware = trim($parts[0] ?? '') ?: null;
        }

        $this->deviceResolver->touch($device, $firmware);

        $commands = $this->commandQueue->takePending($device);

        return $this->plain($this->commandQueue->formatForDevice($commands));
    }

    public function devicecmd(Request $request): Response
    {
        $device = $this->resolveDevice($request);

        if (! $device) {
            return $this->plain("OK\r\n");
        }

        $this->deviceResolver->touch($device);

        $body = $request->getContent();

        if (trim($body) === '') {
            $body = collect($request->except(['SN']))
                ->map(function ($value, $key) {
                    if (! is_scalar($value)) {
                        return null;
                    }

                    return "{$key}={$value}";
                })
                ->filter()
                ->implode("\r\n");
        }

        $this->commandQueue->acknowledgeFromBody($device, $body);

        return $this->plain("OK\r\n");
    }

    public function registry(Request $request): Response
    {
        $device = $this->resolveDevice($request);

        if (! $device) {
            return $this->plain("OK\r\n");
        }

        $this->deviceResolver->touch($device);

        return $this->plain($this->cdataService->handleOptionsHandshake($device));
    }

    protected function resolveDevice(Request $request): ?\App\Models\ZktDevice
    {
        $sn = $request->query('SN') ?? $request->input('SN');

        return $this->deviceResolver->resolve(is_string($sn) ? $sn : null);
    }

    protected function plain(string $content): Response
    {
        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            // F18 copies this clock face onto the display; send local wall time.
            'Date' => AdmsCdataService::deviceClockDateHeader(),
        ]);
    }
}
