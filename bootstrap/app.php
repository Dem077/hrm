<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/adms.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');

        $middleware->validateCsrfTokens(except: [
            'iclock/*',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 403) {
                return null;
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $exception->getMessage() ?: 'Forbidden.',
                ], 403);
            }

            if ($request->header('X-Inertia')) {
                return Inertia::render('Errors/Forbidden', [
                    'message' => $exception->getMessage() ?: 'You do not have permission to access this page.',
                ])->toResponse($request)->setStatusCode(403);
            }

            return null;
        });
    })->create();
