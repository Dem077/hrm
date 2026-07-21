<?php

use App\Enums\ZktAdmsCommandStatus;
use App\Enums\ZktConnectionMode;
use App\Models\ZktAdmsCommand;
use App\Models\ZktDevice;
use App\Services\Zkt\ZktDeviceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('queues adms SyncTime option for attendance machines', function () {
    config(['zkt.time_sync_interval_seconds' => 60]);

    $device = ZktDevice::query()->create([
        'name' => 'Cloud Gate',
        'ip_address' => '0.0.0.0',
        'port' => 4370,
        'protocol' => 'tcp',
        'connection_mode' => ZktConnectionMode::AdmsPush,
        'serial_number' => 'SN-TIME-1',
        'is_active' => true,
        'auto_sync' => false,
        'machine_type' => 'attendance',
    ]);

    $this->artisan('zkt:sync-time')
        ->assertSuccessful();

    expect(ZktAdmsCommand::query()->where('zkt_device_id', $device->id)->count())->toBe(1)
        ->and(ZktAdmsCommand::query()->first()->payload)->toBe('SET OPTION SyncTime=60')
        ->and(ZktAdmsCommand::query()->first()->status)->toBe(ZktAdmsCommandStatus::Pending);
});

it('syncs time on local tcp attendance machines', function () {
    $device = ZktDevice::query()->create([
        'name' => 'Main Gate',
        'ip_address' => '192.168.1.50',
        'port' => 4370,
        'protocol' => 'tcp',
        'connection_mode' => ZktConnectionMode::TcpPull,
        'is_active' => true,
        'auto_sync' => false,
        'machine_type' => 'attendance',
    ]);

    $this->mock(ZktDeviceClient::class, function ($mock) use ($device) {
        $mock->shouldReceive('syncDeviceTime')
            ->once()
            ->with(Mockery::on(fn (ZktDevice $d) => $d->id === $device->id))
            ->andReturn([
                'device_time_before' => '2026-07-21 11:00:00',
                'device_time_after' => '2026-07-21 11:23:00',
                'server_time' => '2026-07-21 11:23:00',
            ]);
    });

    $this->artisan('zkt:sync-time')
        ->assertSuccessful();
});

it('skips access machines and unmanaged placeholders', function () {
    ZktDevice::query()->create([
        'name' => 'Door Access',
        'ip_address' => '192.168.1.60',
        'port' => 4370,
        'protocol' => 'tcp',
        'is_active' => true,
        'machine_type' => 'access',
    ]);

    ZktDevice::query()->create([
        'name' => 'Attendance Sheet',
        'ip_address' => '0.0.0.0',
        'port' => 4370,
        'protocol' => 'tcp',
        'is_active' => true,
        'machine_type' => 'attendance',
    ]);

    $this->mock(ZktDeviceClient::class, function ($mock) {
        $mock->shouldNotReceive('syncDeviceTime');
    });

    $this->artisan('zkt:sync-time')
        ->assertSuccessful();
});
