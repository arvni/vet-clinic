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
use App\Modules\Payments\Gateways\FakePaymentGateway;
use App\Modules\Payments\Gateways\PaymentGateway;
use App\Modules\Payments\Gateways\PaymentUnavailableException;
use App\Modules\Payments\Gateways\ZarinpalGateway;
use App\Modules\Pets\Models\Pet;
use App\Modules\Pets\PetPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->app->singleton(PaymentGateway::class, fn (): PaymentGateway => $this->paymentGateway());
    }

    private function paymentGateway(): PaymentGateway
    {
        return match (config('payments.driver')) {
            // The fake approves everything, so a production server must never reach it.
            'fake' => $this->app->isProduction()
                ? throw new PaymentUnavailableException('Fake payment driver refused in production')
                : new FakePaymentGateway,
            'zarinpal' => new ZarinpalGateway(
                (string) config('payments.zarinpal.merchant_id'),
                (string) config('payments.zarinpal.base_url'),
            ),
            default => throw new InvalidArgumentException('Unknown payment driver ['.config('payments.driver').']'),
        };
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

        // Links (such as the payment callback) must use the public address, not the CDN's view of us.
        if ($this->app->isProduction()) {
            URL::forceRootUrl((string) config('app.url'));
        }

        // Coarse per-IP guard on all auth endpoints; finer limits live in OtpService.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        Gate::policy(Pet::class, PetPolicy::class);
        Gate::define('manage-staff', fn (User $user) => StaffAccess::check($user, Role::Admin));
        Gate::define('manage-catalog', fn (User $user) => StaffAccess::check($user, Role::Admin));
        Gate::define('manage-schedules', fn (User $user) => StaffAccess::check($user, Role::Admin));
        Gate::define('handle-emergencies', fn (User $user) => StaffAccess::check($user, Role::Dispatcher, Role::Vet, Role::Admin));
        Gate::define('manage-invoices', fn (User $user) => StaffAccess::check($user, Role::Dispatcher, Role::Admin));
        Gate::define('manage-refunds', fn (User $user) => StaffAccess::check($user, Role::Admin));
        Gate::define('prescribe', fn (User $user) => StaffAccess::check($user, Role::Vet, Role::Admin));
        Gate::define('manage-zones', fn (User $user) => StaffAccess::check($user, Role::Admin));
    }
}
