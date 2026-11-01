<?php

declare(strict_types=1);

namespace App\Modules\Identity\Otp;

use App\Modules\Identity\Sms\SmsDeliveryException;
use App\Modules\Identity\Sms\SmsSender;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One-time codes for login (FR-1.1) and staff 2FA (FR-1.2).
 *
 * Codes are stored hashed, expire after config('otp.ttl_seconds'), are burned
 * after config('otp.max_attempts') wrong guesses, and requests are rate-limited
 * per mobile and per IP.
 */
class OtpService
{
    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_STAFF = 'staff';

    public function __construct(private readonly Cache $cache, private readonly SmsSender $sms) {}

    /**
     * @return array{resend_after: int, expires_in: int}
     *
     * @throws OtpException
     */
    public function request(string $mobile, string $purpose, string $locale, ?string $ip): array
    {
        $cooldownKey = $this->key('cooldown', $purpose, $mobile);
        if (($until = $this->cache->get($cooldownKey)) !== null) {
            throw OtpException::cooldown(max(1, (int) $until - now()->getTimestamp()));
        }

        $window = (int) config('otp.request_window_seconds');
        foreach ([
            'otp-mobile:'.$mobile => (int) config('otp.max_requests_per_mobile'),
            'otp-ip:'.($ip ?? 'unknown') => (int) config('otp.max_requests_per_ip'),
        ] as $limiterKey => $max) {
            if (RateLimiter::tooManyAttempts($limiterKey, $max)) {
                throw OtpException::tooManyRequests(RateLimiter::availableIn($limiterKey));
            }
        }

        $length = (int) config('otp.length');
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
        $ttl = (int) config('otp.ttl_seconds');
        $resendAfter = (int) config('otp.resend_after_seconds');

        $template = config("sms.templates.otp.{$locale}") ?? config('sms.templates.otp.fa');

        try {
            $this->sms->sendTemplate($mobile, (string) $template, ['code' => $code]);
        } catch (SmsDeliveryException $e) {
            Log::warning('OTP delivery failed', ['mobile' => $mobile, 'error' => $e->getMessage()]);
            throw OtpException::deliveryFailed();
        }

        // Count only requests that actually sent a code.
        RateLimiter::hit('otp-mobile:'.$mobile, $window);
        RateLimiter::hit('otp-ip:'.($ip ?? 'unknown'), $window);

        $this->cache->put($this->key('code', $purpose, $mobile), [
            'hash' => $this->hash($code, $mobile, $purpose),
            'attempts' => 0,
            'expires_at' => now()->getTimestamp() + $ttl,
        ], $ttl);
        $this->cache->put($cooldownKey, now()->getTimestamp() + $resendAfter, $resendAfter);

        return ['resend_after' => $resendAfter, 'expires_in' => $ttl];
    }

    /** @throws OtpException */
    public function verify(string $mobile, string $code, string $purpose): void
    {
        $key = $this->key('code', $purpose, $mobile);
        /** @var array{hash: string, attempts: int, expires_at: int}|null $entry */
        $entry = $this->cache->get($key);

        if ($entry === null || $entry['expires_at'] <= now()->getTimestamp()) {
            throw OtpException::invalid();
        }

        if (hash_equals($entry['hash'], $this->hash($code, $mobile, $purpose))) {
            $this->cache->forget($key);
            $this->cache->forget($this->key('cooldown', $purpose, $mobile));

            return;
        }

        $entry['attempts']++;
        $remaining = $entry['expires_at'] - now()->getTimestamp();
        if ($entry['attempts'] >= (int) config('otp.max_attempts') || $remaining <= 0) {
            $this->cache->forget($key);
        } else {
            // Re-store with the remaining lifetime so wrong guesses never extend the code's validity.
            $this->cache->put($key, $entry, $remaining);
        }

        throw OtpException::invalid();
    }

    private function key(string $kind, string $purpose, string $mobile): string
    {
        return "otp:{$kind}:{$purpose}:{$mobile}";
    }

    private function hash(string $code, string $mobile, string $purpose): string
    {
        return hash_hmac('sha256', "{$purpose}|{$mobile}|{$code}", (string) config('app.key'));
    }
}
