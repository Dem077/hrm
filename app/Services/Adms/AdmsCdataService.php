<?php

namespace App\Services\Adms;

use App\Models\Employee;
use App\Models\ZktDevice;
use Illuminate\Support\Facades\Log;

class AdmsCdataService
{
    public function __construct(
        protected AdmsAttendanceIngestService $attendanceIngest,
        protected AdmsCommandQueue $commandQueue,
        protected AdmsUserCommandBuilder $userCommandBuilder,
    ) {}

    public function handleOptionsHandshake(ZktDevice $device): string
    {
        $stamp = now()->format('YmdHis');

        $lines = [
            'GET OPTION FROM DB',
            'Stamp='.$stamp,
            'OpStamp='.$stamp,
            'ErrorDelay=60',
            'Delay=30',
            'TransTimes=00:00;14:00',
            'TransInterval=1',
            'TransFlag=1111000000',
            'TimeZone=0',
            'Realtime=1',
            'Encrypt=0',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    public function handleTable(ZktDevice $device, ?string $table, string $body): string
    {
        $table = strtoupper(trim((string) $table));

        return match ($table) {
            'ATTLOG' => $this->handleAttLog($device, $body),
            'OPERLOG' => $this->handleOperLog($device, $body),
            'OPTIONS', '' => "OK\r\n",
            default => $this->handleUnknownTable($device, $table, $body),
        };
    }

    protected function handleAttLog(ZktDevice $device, string $body): string
    {
        $result = $this->attendanceIngest->ingestAttLog($device, $body);

        Log::info('ADMS ATTLOG ingested.', [
            'device_id' => $device->id,
            'stored' => $result['stored'],
            'skipped' => $result['skipped'],
        ]);

        return "OK\r\n";
    }

    protected function handleOperLog(ZktDevice $device, string $body): string
    {
        // Accept OPERLOG / USERINFO-style lines for audit; apply credential hints when present.
        $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with(strtoupper($line), 'USER') || str_contains($line, 'PIN=')) {
                $this->maybeApplyUserInfoLine($line);
            }
        }

        Log::info('ADMS OPERLOG received.', [
            'device_id' => $device->id,
            'bytes' => strlen($body),
        ]);

        return "OK\r\n";
    }

    protected function handleUnknownTable(ZktDevice $device, string $table, string $body): string
    {
        Log::info('ADMS unknown table payload accepted.', [
            'device_id' => $device->id,
            'table' => $table,
            'bytes' => strlen($body),
        ]);

        return "OK\r\n";
    }

    protected function maybeApplyUserInfoLine(string $line): void
    {
        if (! preg_match('/PIN=([^\t\s]+)/i', $line, $pinMatch)) {
            return;
        }

        $employee = Employee::query()->where('staff_id', trim($pinMatch[1]))->first();

        if (! $employee) {
            return;
        }

        $updates = [];

        if (preg_match('/Card=([^\t\s]*)/i', $line, $cardMatch)) {
            $digits = preg_replace('/\D+/', '', $cardMatch[1] ?? '');

            if ($digits !== '' && ! preg_match('/^0+$/', $digits)) {
                $updates['device_card_number'] = $digits;
            }
        }

        if (preg_match('/Passwd=([^\t\s]*)/i', $line, $passMatch) && filled($passMatch[1] ?? null)) {
            $updates['device_password'] = substr((string) $passMatch[1], 0, 8);
        }

        if ($updates !== []) {
            $employee->update($updates);
        }
    }
}
