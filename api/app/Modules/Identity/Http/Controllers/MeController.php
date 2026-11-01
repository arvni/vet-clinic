<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Audit\Audit;
use App\Modules\Identity\Http\Requests\UpdateMeRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController
{
    public function show(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('roles'));
    }

    public function update(UpdateMeRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->update($request->validated());

        if ($request->has('locale')) {
            app()->setLocale($user->locale);
        }

        return new UserResource($user->load('roles'));
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        // API clients authenticate with bearer tokens only, so this is a PersonalAccessToken.
        $user->currentAccessToken()->delete();
        Audit::record('auth.logout', $user);

        return response()->json(status: 204);
    }
}
