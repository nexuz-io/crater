<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware aliases
        $middleware->alias([
            'auth' => \Crater\Http\Middleware\Authenticate::class,
            'bouncer' => \Crater\Http\Middleware\ScopeBouncer::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \Crater\Http\Middleware\RedirectIfAuthenticated::class,
            'customer' => \Crater\Http\Middleware\CustomerRedirectIfAuthenticated::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'install' => \Crater\Http\Middleware\InstallationMiddleware::class,
            'redirect-if-installed' => \Crater\Http\Middleware\RedirectIfInstalled::class,
            'redirect-if-unauthenticated' => \Crater\Http\Middleware\RedirectIfUnauthorized::class,
            'customer-guest' => \Crater\Http\Middleware\CustomerGuest::class,
            'company' => \Crater\Http\Middleware\CompanyMiddleware::class,
            'pdf-auth' => \Crater\Http\Middleware\PdfMiddleware::class,
            'cron-job' => \Crater\Http\Middleware\CronJobMiddleware::class,
            'customer-portal' => \Crater\Http\Middleware\CustomerPortalMiddleware::class,
        ]);

        // Global middleware (applied to every request)
        $middleware->append([
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \Crater\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
            \Crater\Http\Middleware\TrustProxies::class,
            \Crater\Http\Middleware\ConfigMiddleware::class,
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Web group
        $middleware->web(replace: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class => \Crater\Http\Middleware\EncryptCookies::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \Crater\Http\Middleware\VerifyCsrfToken::class,
        ]);

        // API group
        $middleware->api(append: [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:180,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Middleware priority
        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Crater\Http\Middleware\Authenticate::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withCommands([
        \Crater\Console\Commands\ResetApp::class,
        \Crater\Console\Commands\UpdateCommand::class,
        \Crater\Console\Commands\CreateTemplateCommand::class,
        \Crater\Console\Commands\InstallModuleCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        if (database_is_ready() && config('database.default') !== 'sqlite' && ! app()->runningUnitTests()) {
            $schedule->command('check:invoices:status')->daily();
            $schedule->command('check:estimates:status')->daily();

            $recurringInvoices = \Crater\Models\RecurringInvoice::where('status', 'ACTIVE')->get();
            foreach ($recurringInvoices as $recurringInvoice) {
                $timeZone = \Crater\Models\CompanySetting::getSetting('time_zone', $recurringInvoice->company_id);

                $schedule->call(function () use ($recurringInvoice) {
                    $recurringInvoice->generateInvoice();
                })->cron($recurringInvoice->frequency)->timezone($timeZone);
            }
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash([
            'password',
            'password_confirmation',
        ]);
    })
    ->create();
