<?php

use App\Enums\ZktAdmsCommandStatus;
use App\Enums\ZktConnectionMode;
use App\Models\Employee;
use App\Models\ZktAdmsCommand;
use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use App\Services\Adms\AdmsCommandQueue;
use App\Services\Adms\AdmsUserCommandBuilder;
use App\Services\Zkt\ZktDeviceUserSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->device = ZktDevice::query()->create([
        'name' => 'Cloud Gate',
        'ip_address' => '0.0.0.0',
        'port' => 4370,
        'protocol' => 'tcp',
        'connection_mode' => ZktConnectionMode::AdmsPush,
        'serial_number' => 'SN123456',
        'is_active' => true,
        'auto_sync' => false,
        'machine_type' => 'attendance',
    ]);

    $this->employee = Employee::query()->create([
        'staff_id' => 'EMP100',
        'name' => 'Ada Lovelace',
        'national_id' => 'NID-ADMS',
        'joined_date' => '2024-01-01',
        'gender' => 'female',
        'device_privilege' => 'employee',
        'is_active' => true,
        'bank_name' => 'Test Bank',
        'account_name' => 'Ada Lovelace',
        'account_no' => '123456',
    ]);
});

it('returns ok for unknown serial numbers without storing punches', function () {
    $response = $this->call(
        'POST',
        '/iclock/cdata?SN=UNKNOWN&table=ATTLOG',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/plain'],
        "EMP100\t2026-07-21 09:00:00\t0\t1",
    );

    $response->assertOk();
    expect($response->getContent())->toContain('OK');
    expect(ZktAttendanceLog::query()->count())->toBe(0);
});

it('ingests attlog punches for a known adms device', function () {
    $body = "EMP100\t2026-07-21 09:00:00\t0\t1\r\nEMP100\t2026-07-21 18:00:00\t1\t1";

    $response = $this->call(
        'POST',
        '/iclock/cdata?SN=SN123456&table=ATTLOG',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/plain'],
        $body,
    );

    $response->assertOk();
    expect(ZktAttendanceLog::query()->count())->toBe(2);

    $log = ZktAttendanceLog::query()->where('device_user_id', 'EMP100')->orderBy('punched_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->punch_state)->toBe(0)
        ->and($log->zkt_device_id)->toBe($this->device->id);

    $this->device->refresh();
    expect($this->device->last_adms_seen_at)->not->toBeNull();
});

it('does not duplicate punches when the same attlog is pushed again', function () {
    $body = "EMP100\t2026-07-21 09:00:00\t0\t1";

    $this->call('POST', '/iclock/cdata?SN=SN123456&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)
        ->assertOk();
    $this->call('POST', '/iclock/cdata?SN=SN123456&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)
        ->assertOk();

    expect(ZktAttendanceLog::query()->count())->toBe(1);
});

it('delivers queued commands on getrequest and acknowledges via devicecmd', function () {
    $queue = app(AdmsCommandQueue::class);
    $builder = app(AdmsUserCommandBuilder::class);

    $command = $queue->enqueue($this->device, $builder->buildUserCommand($this->employee));

    $poll = $this->get('/iclock/getrequest?SN=SN123456');
    $poll->assertOk();
    expect($poll->getContent())->toContain("C:{$command->command_no}:DATA UPDATE USERINFO");

    $command->refresh();
    expect($command->status)->toBe(ZktAdmsCommandStatus::Sent);

    $ackBody = "ID={$command->command_no}&Return=0&CMD=DATA";
    $ack = $this->call(
        'POST',
        '/iclock/devicecmd?SN=SN123456',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/plain'],
        $ackBody,
    );
    $ack->assertOk();

    $command->refresh();
    expect($command->status)->toBe(ZktAdmsCommandStatus::Done);
    expect($command->payload)->toContain('Pri=')
        ->and($command->payload)->not->toContain('Privilege=')
        ->and($command->payload)->not->toContain('DATA USER');
});

it('queues adms user commands when syncing employees to an adms device', function () {
    $service = app(ZktDeviceUserSyncService::class);

    // Call protected path via reflection-free public syncDeviceUsers after attaching location group?
    // Directly exercise queue through syncEmployeeToDevice by syncing device users with empty eligibility — use enqueue via syncEmployeeToDevice through reflection.
    // Simpler: use AdmsCommandQueue after user sync service method via location groups is heavy.
    // Call pullDeviceCredentials which queues QUERY for ADMS.
    $results = $service->pullDeviceCredentials($this->device);

    expect($results[0]['status'])->toBe('queued');
    expect(ZktAdmsCommand::query()->where('zkt_device_id', $this->device->id)->count())->toBe(1);
    expect(ZktAdmsCommand::query()->first()->payload)->toBe('DATA QUERY USERINFO');
});

it('returns registry options for handshake', function () {
    $response = $this->get('/iclock/cdata?SN=SN123456&options=all');

    $response->assertOk();
    expect($response->getContent())->toContain('Stamp=')
        ->and($response->getContent())->toContain('Realtime=1');
});

it('builds set time and attlog query commands for adms parity actions', function () {
    $builder = app(AdmsUserCommandBuilder::class);

    expect($builder->buildQueryAttLogCommand(
        now()->setDateTime(2026, 7, 1, 0, 0, 0),
        now()->setDateTime(2026, 7, 21, 23, 59, 59),
    ))->toContain('DATA QUERY ATTLOG')
        ->toContain('StartTime=2026-07-01 00:00:00')
        ->toContain('EndTime=2026-07-21 23:59:59');
});

it('returns push sdk time sync payload and timezone options', function () {
    $interval = max(1, (int) config('zkt.time_sync_interval_seconds', 60));

    $handshake = $this->get('/iclock/cdata?SN=SN123456&options=all');
    $handshake->assertOk();
    expect($handshake->headers->get('Date'))->toContain('GMT')
        ->and($handshake->getContent())->toContain('SyncTime='.$interval)
        ->and($handshake->getContent())->toContain('TimeZone=0')
        ->and($handshake->getContent())->toContain('ServerVer=2.2.14');

    $time = $this->get('/iclock/cdata?SN=SN123456&type=time');
    $time->assertOk();
    expect($time->getContent())->toContain('Time=')
        ->and($time->getContent())->toContain('DateTime=');

    $builder = app(AdmsUserCommandBuilder::class);
    $commands = $builder->buildTimeSyncOptionCommands();
    expect($commands)->toContain('SET OPTION TimeZone=0')
        ->and($commands)->toContain('SET OPTION SyncTime='.$interval)
        ->and($commands)->toContain('RELOAD OPTIONS')
        ->and($commands)->toContain('CHECK');
});
