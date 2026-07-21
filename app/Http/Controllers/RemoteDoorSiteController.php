<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRemoteDoorSiteRequest;
use App\Http\Requests\UpdateRemoteDoorSiteRequest;
use App\Models\RemoteDoorSite;
use Illuminate\Http\RedirectResponse;

class RemoteDoorSiteController extends Controller
{
    public function store(StoreRemoteDoorSiteRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('employee_ids');
        $site = RemoteDoorSite::query()->create($data);
        $site->employees()->sync($request->input('employee_ids', []));

        return back()->with('success', 'Remote door site created successfully.');
    }

    public function update(UpdateRemoteDoorSiteRequest $request, RemoteDoorSite $remoteDoorSite): RedirectResponse
    {
        $data = $request->safe()->except('employee_ids');
        $remoteDoorSite->update($data);
        $remoteDoorSite->employees()->sync($request->input('employee_ids', []));

        return back()->with('success', 'Remote door site updated successfully.');
    }

    public function destroy(RemoteDoorSite $remoteDoorSite): RedirectResponse
    {
        $remoteDoorSite->employees()->detach();
        $remoteDoorSite->delete();

        return back()->with('success', 'Remote door site deleted.');
    }
}
