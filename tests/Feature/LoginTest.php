<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createEmployeeUser(string $password = 'secret-password'): array
{
    $user = User::factory()->create([
        'email' => 'employee@example.com',
        'password' => $password,
    ]);

    $employee = Employee::query()->create([
        'staff_id' => 'STF-300',
        'name' => 'Login Test',
        'national_id' => '42201-1234567-1',
        'email' => 'employee@example.com',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'is_active' => true,
        'user_id' => $user->id,
    ]);

    return [$user, $employee];
}

it('logs in with email', function () {
    [$user] = createEmployeeUser();

    $response = $this->post('/login', [
        'login' => 'employee@example.com',
        'password' => 'secret-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('logs in with staff id', function () {
    [$user] = createEmployeeUser();

    $response = $this->post('/login', [
        'login' => 'STF-300',
        'password' => 'secret-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('logs in with national id', function () {
    [$user] = createEmployeeUser();

    $response = $this->post('/login', [
        'login' => '42201-1234567-1',
        'password' => 'secret-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid login credentials', function () {
    createEmployeeUser();

    $response = $this->from('/login')->post('/login', [
        'login' => 'STF-300',
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('login');
    $this->assertGuest();
});
