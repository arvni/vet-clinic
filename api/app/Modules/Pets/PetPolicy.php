<?php

declare(strict_types=1);

namespace App\Modules\Pets;

use App\Models\User;
use App\Modules\Identity\Role;
use App\Modules\Identity\StaffAccess;
use App\Modules\Pets\Models\Pet;
use App\Modules\Pets\Models\PetDocument;

class PetPolicy
{
    public function view(User $user, Pet $pet): bool
    {
        return $this->owns($user, $pet) || StaffAccess::check($user);
    }

    /** Edit profile, add weights, allergies and documents. */
    public function update(User $user, Pet $pet): bool
    {
        return $this->owns($user, $pet) || StaffAccess::check($user, Role::Vet, Role::Vaccinator, Role::Admin, Role::Dispatcher);
    }

    public function delete(User $user, Pet $pet): bool
    {
        return $this->owns($user, $pet);
    }

    public function transfer(User $user, Pet $pet): bool
    {
        return $this->owns($user, $pet);
    }

    public function verifyDocument(User $user, PetDocument $document): bool
    {
        return StaffAccess::check($user, Role::Vet, Role::Vaccinator, Role::Dispatcher, Role::Admin);
    }

    private function owns(User $user, Pet $pet): bool
    {
        return $pet->owner_id === $user->id && $user->tokenCan('owner');
    }
}
