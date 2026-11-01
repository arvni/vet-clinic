<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Identity\Role;
use App\Modules\Identity\Sms\AllowlistSmsSender;
use App\Modules\Identity\Sms\FakeSmsSender;
use App\Modules\Identity\Sms\KavenegarSmsSender;
use App\Modules\Identity\Sms\SmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsSender::class, function (): SmsSender {
            $sender = match (config('sms.driver')) {
                'fake' => new FakeSmsSender,
                'kavenegar' => new KavenegarSmsSender(
                    (string) config('sms.kavenegar.api_key'),
                    (string) config('sms.kavenegar.base_url'),
                ),
                default => throw new InvalidArgumentException('Unknown SMS driver ['.config('sms.driver').']'),
            };

            $allowlist = config('sms.allowlist', []);

            return $allowlist !== [] && ! $sender instanceof FakeSmsSender
                ? new AllowlistSmsSender($sender, $allowlist)
                : $sender;
        });
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Coarse per-IP guard on all auth endpoints; finer limits live in OtpService.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        Gate::define('manage-staff', fn (User $user) => $user->hasRole(Role::Admin->value));
    }
}
