<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Audit\Audit;
use App\Modules\Identity\Http\Requests\OtpRequestRequest;
use App\Modules\Identity\Http\Requests\OtpVerifyRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Otp\OtpService;
use App\Modules\Identity\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Owner sign-in and sign-up by SMS code (FR-1.1). */
class OtpAuthController
{
    public function __construct(private readonly OtpService $otp) {}

    public function request(OtpRequestRequest $request): JsonResponse
    {
        $locale = User::query()->where('mobile', $request->mobile())->value('locale') ?? app()->getLocale();

        $result = $this->otp->request($request->mobile(), OtpService::PURPOSE_LOGIN, $locale, $request->ip());

        return response()->json($result, 202);
    }

    public function verify(OtpVerifyRequest $request): JsonResponse
    {
        $mobile = $request->mobile();

        $existing = User::query()->where('mobile', $mobile)->first();
        if ($existing?->is_staff) {
            // Staff must use password + OTP; refuse before consuming the code.
            return response()->json(['message' => __('auth.staff_use_staff_login')], 422);
        }
        if ($existing !== null && ! $existing->isActive()) {
            return response()->json(['message' => __('auth.suspended')], 403);
        }

        $this->otp->verify($mobile, (string) $request->validated('code'), OtpService::PURPOSE_LOGIN);

        $user = DB::transaction(function () use ($existing, $mobile): User {
            $user = $existing ?? User::create([
                'mobile' => $mobile,
                'locale' => app()->getLocale(),
            ]);

            if ($user->wasRecentlyCreated) {
                $user->assignRole(Role::Owner->value);
            }

            $user->forceFill([
                'mobile_verified_at' => $user->mobile_verified_at ?? now(),
                'last_login_at' => now(),
            ])->saveQuietly();

            return $user;
        });

        Audit::record('auth.login', $user, after: ['method' => 'otp', 'new_account' => $user->wasRecentlyCreated], actorId: $user->id);

        $token = $user->createToken($request->validated('device_name') ?? 'spa', ['owner'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('roles')),
            'new_account' => $user->wasRecentlyCreated,
        ]);
    }
}
