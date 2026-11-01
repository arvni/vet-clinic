<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Audit\Audit;
use App\Modules\Identity\Http\Requests\StaffLoginRequest;
use App\Modules\Identity\Http\Requests\StaffVerifyRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Otp\OtpService;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Staff sign-in: password, then SMS code (FR-1.2). */
class StaffAuthController
{
    private const MAX_PASSWORD_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    public function __construct(private readonly OtpService $otp, private readonly Cache $cache) {}

    public function login(StaffLoginRequest $request): JsonResponse
    {
        $mobile = $request->mobile();
        $limiterKey = 'staff-login:'.$mobile.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_PASSWORD_ATTEMPTS)) {
            $retry = RateLimiter::availableIn($limiterKey);

            return response()->json(['message' => __('auth.throttle', ['seconds' => $retry]), 'retry_after' => $retry], 429, ['Retry-After' => (string) $retry]);
        }

        $user = User::query()->where('mobile', $mobile)->where('is_staff', true)->first();

        // Always run a hash check so response time does not reveal whether the account exists.
        $valid = Hash::check((string) $request->validated('password'), $user->password ?? Hash::make(Str::random(16)));

        if ($user === null || ! $valid || ! $user->isStaffMember()) {
            RateLimiter::hit($limiterKey, self::LOCKOUT_SECONDS);
            if ($user !== null) {
                Audit::record('auth.staff_password_failed', $user, actorId: $user->id);
            }

            return response()->json(['message' => __('auth.failed')], 422);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => __('auth.suspended')], 403);
        }

        RateLimiter::clear($limiterKey);

        $result = $this->otp->request($mobile, OtpService::PURPOSE_STAFF, $user->locale, $request->ip());

        $challenge = Str::random(40);
        $this->cache->put($this->challengeKey($challenge), $user->id, (int) config('otp.staff_challenge_ttl_seconds'));

        return response()->json(['challenge' => $challenge] + $result, 202);
    }

    public function verify(StaffVerifyRequest $request): JsonResponse
    {
        $key = $this->challengeKey((string) $request->validated('challenge'));
        $userId = $this->cache->get($key);
        $user = $userId !== null ? User::query()->find($userId) : null;

        if ($user === null || ! $user->isStaffMember() || ! $user->isActive()) {
            return response()->json(['message' => __('auth.challenge_expired')], 422);
        }

        $this->otp->verify($user->mobile, (string) $request->validated('code'), OtpService::PURPOSE_STAFF);
        $this->cache->forget($key);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Audit::record('auth.login', $user, after: ['method' => 'staff_2fa'], actorId: $user->id);

        $token = $user->createToken('staff-spa', ['staff'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('roles')),
        ]);
    }

    private function challengeKey(string $challenge): string
    {
        return 'staff-challenge:'.hash('sha256', $challenge);
    }
}
