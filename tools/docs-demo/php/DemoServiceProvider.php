<?php

declare(strict_types=1);

namespace FernwayDemo;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Monolog\LogRecord;

/**
 * Kit-only provider, never part of the Fernway app (it is not in its git history or on the Skills page):
 *
 * - log lines are stamped with the story's clock (Carbon's test time) instead of the wall clock;
 * - a request of the traffic script names its member in X-Demo-Member and skips CSRF;
 * - Stripe answers 401 to the customer sync, as it does to a key that is not live yet;
 * - `fernway:demo-seed` writes the rows of the app.
 */
class DemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SeedCommand::class, DispatchCommand::class, TeamCommand::class]);
        }

        Http::fake(['api.stripe.com/*' => Http::response(['error' => [
            'message' => 'No such API key: sk_test_FernwayDemoNotALiveKey',
            'type' => 'invalid_request_error',
        ]], 401)]);

        $this->app->booted(function (): void {
            $logger = Log::channel()->getLogger();
            if (method_exists($logger, 'pushProcessor')) {
                $logger->pushProcessor(static fn (LogRecord $record): LogRecord => Carbon::hasTestNow()
                    ? $record->with(datetime: new \DateTimeImmutable(Carbon::now()->format('Y-m-d H:i:s.u'), new \DateTimeZone('UTC')))
                    : $record);
            }

            if (! $this->app->runningInConsole() && ($member = request()->header('X-Demo-Member')) !== null) {
                ValidateCsrfToken::except(['*']);
                Auth::onceUsingId((int) $member);
            }
        });
    }
}
