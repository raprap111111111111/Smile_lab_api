<?php

// DELETE this import:
// use App\Http\Middleware\Cors;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        attributes: ['middleware' => ['auth:api']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ❌ REMOVE this line — causes double CORS headers
        // $middleware->append(Cors::class);

        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'role'               => RoleMiddleware::class,
            'permission'         => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('reminders:dispatch')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('invoices:notify-overdue')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('appointments:send-followups')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Hourly rather than dailyAt(): the send hour is admin-tunable via
        // inventory_low_stock_alert_hour, and the schedule is built at boot
        // from static config, so the command checks the hour itself.
        $schedule->command('inventory:check-levels')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return null;
        });
    })->create();