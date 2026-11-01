<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Audit\Audit;
use App\Modules\Identity\Http\Requests\StoreStaffRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Admin-managed staff accounts (FR-1.2). */
class StaffController
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('manage-staff');

        return UserResource::collection(
            User::query()->where('is_staff', true)->with('roles')->orderBy('name')->paginate(50)
        );
    }

    public function store(StoreStaffRequest $request): UserResource
    {
        Gate::authorize('manage-staff');

        $staff = DB::transaction(function () use ($request): User {
            $staff = User::create([
                'mobile' => $request->mobile(),
                'name' => $request->validated('name'),
                'password' => $request->validated('password'),
                'locale' => $request->validated('locale') ?? 'fa',
                'is_staff' => true,
            ]);
            $staff->syncRoles($request->validated('roles'));

            return $staff;
        });

        Audit::record('staff.roles_assigned', $staff, after: ['roles' => $request->validated('roles')]);

        return new UserResource($staff->load('roles'));
    }
}
