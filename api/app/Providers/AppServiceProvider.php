<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Identity\Role;
use App\Modules\Identity\Sms\AllowlistSmsSender;
use App\Modules\Identity\Sms\FakeSmsSender;
use App\Modules\Identity\Sms\KavenegarSmsSender;
use App\Modules\Identity\Sms\SmsSender;
use App\Modules\Identity\StaffAccess;
use App\Modules\Locations\Maps\FakeMapProvider;
use App\Modules\Locations\Maps\MapProvider;
use App\Modules\Locations\Maps\NeshanMapProvider;
use App\Modules\Pets\Models\Pet;
use App\Modules\Pets\PetPolicy;
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

        $this->app->singleton(MapProvider::class, fn (): MapProvider => $this->mapProvider());
    }

    private function mapProvider(): MapProvider
    {
        return match (config('maps.driver')) {
            'fake' => new FakeMapProvider,
            'neshan' => new NeshanMapProvider(
                (string) config('maps.neshan.api_key'),
                (string) config('maps.neshan.base_url'),
            ),
            default => throw new InvalidArgumentException('Unknown map driver ['.config('maps.driver').']'),
        };
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Coarse per-IP guard on all auth endpoints; finer limits live in OtpService.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        Gate::policy(Pet::class, PetPolicy::class);
        Gate::define('manage-staff', fn (User $user) => StaffAccess::check($user, Role::Admin));
        Gate::define('manage-catalog', fn (User $user) => StaffAccess::check($user, Role::Admin));
        Gate::define('manage-schedules', fn (User $user) => StaffAccess::check($user, Role::Admin));
        Gate::define('handle-emergencies', fn (User $user) => StaffAccess::check($user, Role::Dispatcher, Role::Vet, Role::Admin));
        Gate::define('manage-invoices', fn (User $user) => StaffAccess::check($user, Role::Dispatcher, Role::Admin));
        Gate::define('manage-zones', fn (User $user) => StaffAccess::check($user, Role::Admin));
    }
}
