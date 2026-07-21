<?php

namespace App\Services\Attendance;

use App\Enums\AttendancePunchSource;
use App\Models\RemoteDoorOpenLog;
use App\Models\RemoteDoorSite;
use App\Models\SelfPunchSite;
use App\Models\ZktAttendanceLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MobilePunchAccessLogService
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function punchLogs(Request $request): LengthAwarePaginator
    {
        return $this->applyPunchFilters(
            ZktAttendanceLog::query()
                ->where('source', AttendancePunchSource::SelfApp)
                ->with([
                    'employee:id,staff_id,name',
                    'selfPunchSite:id,name,code',
                ]),
            $request,
        )
            ->latest('punched_at')
            ->paginate(25, ['*'], 'punch_page')
            ->withQueryString()
            ->through(fn (ZktAttendanceLog $log) => $log->toMobilePunchAuditArray());
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function doorOpenLogs(Request $request): LengthAwarePaginator
    {
        return $this->applyDoorFilters(
            RemoteDoorOpenLog::query()
                ->with([
                    'employee:id,staff_id,name',
                    'user:id,name,email',
                    'site:id,name,code',
                    'device:id,name',
                ]),
            $request,
        )
            ->latest('opened_at')
            ->paginate(25, ['*'], 'door_page')
            ->withQueryString()
            ->through(fn (RemoteDoorOpenLog $log) => $log->toMobilePunchAuditArray());
    }

    /**
     * @return list<array{id: int, name: string, code: string|null}>
     */
    public function punchSiteOptions(): array
    {
        return SelfPunchSite::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (SelfPunchSite $site) => [
                'id' => $site->id,
                'name' => $site->name,
                'code' => $site->code,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, code: string|null}>
     */
    public function doorSiteOptions(): array
    {
        return RemoteDoorSite::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (RemoteDoorSite $site) => [
                'id' => $site->id,
                'name' => $site->name,
                'code' => $site->code,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<ZktAttendanceLog>  $query
     * @return Builder<ZktAttendanceLog>
     */
    protected function applyPunchFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('search'), function (Builder $inner) use ($request) {
                $search = $request->string('search')->toString();

                $inner->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery->where('device_user_id', 'like', "%{$search}%")
                        ->orWhere('client_ip', 'like', "%{$search}%")
                        ->orWhere('request_ip', 'like', "%{$search}%")
                        ->orWhere('client_device_id', 'like', "%{$search}%")
                        ->orWhereHas('employee', function (Builder $employeeQuery) use ($search) {
                            $employeeQuery->where('staff_id', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('selfPunchSite', function (Builder $siteQuery) use ($search) {
                            $siteQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('site_id'), fn (Builder $inner) => $inner->where('self_punch_site_id', $request->integer('site_id')))
            ->when($request->filled('client_device_id'), fn (Builder $inner) => $inner->where('client_device_id', $request->string('client_device_id')->toString()))
            ->when($request->filled('date_from'), fn (Builder $inner) => $inner->where('punched_at', '>=', $this->startOfDayUtc($request->string('date_from')->toString())))
            ->when($request->filled('date_to'), fn (Builder $inner) => $inner->where('punched_at', '<=', $this->endOfDayUtc($request->string('date_to')->toString())));
    }

    /**
     * @param  Builder<RemoteDoorOpenLog>  $query
     * @return Builder<RemoteDoorOpenLog>
     */
    protected function applyDoorFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('search'), function (Builder $inner) use ($request) {
                $search = $request->string('search')->toString();

                $inner->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery->where('client_ip', 'like', "%{$search}%")
                        ->orWhereHas('employee', function (Builder $employeeQuery) use ($search) {
                            $employeeQuery->where('staff_id', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('site', function (Builder $siteQuery) use ($search) {
                            $siteQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('device', fn (Builder $deviceQuery) => $deviceQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('site_id'), fn (Builder $inner) => $inner->where('remote_door_site_id', $request->integer('site_id')))
            ->when($request->filled('date_from'), fn (Builder $inner) => $inner->where('opened_at', '>=', $this->startOfDayUtc($request->string('date_from')->toString())))
            ->when($request->filled('date_to'), fn (Builder $inner) => $inner->where('opened_at', '<=', $this->endOfDayUtc($request->string('date_to')->toString())));
    }

    protected function startOfDayUtc(string $date): Carbon
    {
        return Carbon::parse($date, config('app.timezone', 'UTC'))->startOfDay()->utc();
    }

    protected function endOfDayUtc(string $date): Carbon
    {
        return Carbon::parse($date, config('app.timezone', 'UTC'))->endOfDay()->utc();
    }
}
