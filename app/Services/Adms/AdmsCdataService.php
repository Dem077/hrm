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
        $now = now();

        $lines = [
            'GET OPTION FROM: '.$device->serial_number,
            'Stamp='.$now->format('Y-m-d H:i:s'),
            'OpStamp='.$now->timestamp,
            'ErrorDelay=60',
            'Delay=30',
            'TransTimes=00:00;14:00',
            'TransInterval=1',
            'TransFlag=TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP',
            // TimeZone=0: wall clock comes from the HTTP Date header, which we send as
            // local server time (F18 copies Date HH:MM:SS onto the display). Adding a
            // non-zero TimeZone on top of that would double-shift the clock.
            'TimeZone=0',
            'Realtime=1',
            'Encrypt=0',
            'Timeout=60',
            'SyncTime='.max(1, (int) config('zkt.time_sync_interval_seconds', 60)),
            'ServerVer=2.2.14',
            'ATTLOGStamp='.$now->format('Y-m-d H:i:s'),
            'OPERLOGStamp='.$now->format('Y-m-d H:i:s'),
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Hours east of UTC for classic Push TimeZone= (e.g. 5 for Pakistan).
     */
    public static function timezoneHoursFromUtc(?\DateTimeInterface $at = null): int
    {
        $at ??= now();

        return (int) round($at->getOffset() / 3600);
    }

    /**
     * F18 / classic Push often copies the HTTP Date clock face onto the device
     * display and ignores the GMT meaning. Send local wall time labeled as GMT.
     */
    public static function deviceClockDateHeader(?\DateTimeInterface $at = null): string
    {
        $at ??= now();

        return $at->format('D, d M Y H:i:s').' GMT';
    }

    /**
     * Push SDK time sync for GET /iclock/cdata?SN=...&type=time
     */
    public function handleTimeSyncResponse(): string
    {
        $local = now()->format('Y-m-d H:i:s');

        return 'Time='.$local."\r\n"
            .'DateTime='.$local."\r\n";
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
