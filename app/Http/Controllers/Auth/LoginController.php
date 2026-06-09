<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->resolveUserFromLogin($validated['login']);

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function resolveUserFromLogin(string $login): ?User
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = User::query()->where('email', $login)->first();

            if ($user && $this->userCanLogin($user)) {
                return $user;
            }
        }

        $employee = Employee::query()
            ->where('staff_id', $login)
            ->orWhere('national_id', $login)
            ->when(
                filter_var($login, FILTER_VALIDATE_EMAIL),
                fn ($query) => $query->orWhere('email', $login),
            )
            ->first();

        if (! $employee || ! $employee->is_active || ! $employee->user_id) {
            return null;
        }

        $user = User::query()->find($employee->user_id);

        return $user && $this->userCanLogin($user) ? $user : null;
    }

    protected function userCanLogin(User $user): bool
    {
        $employee = $user->employee;

        return ! $employee || $employee->is_active;
    }
}
