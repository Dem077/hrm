<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNationalityRequest;
use App\Models\Nationality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class NationalityController extends Controller
{
    public function store(StoreNationalityRequest $request): RedirectResponse
    {
        $name = $request->validated('name');

        Nationality::query()->create([
            'name' => $name,
            'sort_order' => (int) Nationality::query()->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return back()->with('success', "Nationality \"{$name}\" added.");
    }

    public function destroy(Nationality $nationality): RedirectResponse
    {
        $user = request()->user();

        if (! $user?->can('employees.create') && ! $user?->can('employees.update')) {
            abort(403);
        }

        if ($nationality->isInUse()) {
            throw ValidationException::withMessages([
                'nationality' => 'This nationality is assigned to employees and cannot be deleted.',
            ]);
        }

        $name = $nationality->name;
        $nationality->delete();

        return back()->with('success', "Nationality \"{$name}\" removed.");
    }
}
