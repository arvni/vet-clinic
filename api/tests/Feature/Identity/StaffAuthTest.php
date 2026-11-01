<?php

use App\Models\User;
use App\Modules\Identity\Role;
use App\Modules\Identity\Sms\FakeSmsSender;
use App\Modules\Identity\Sms\SmsSender;
use Illuminate\Testing\TestResponse;

function staffLogin(string $password = 'Secret12345'): TestResponse
{
    return test()->postJson('/api/v1/auth/staff/login', ['mobile' => '09121112233', 'password' => $password]);
}

function staffCode(): string
{
    /** @var FakeSmsSender $sms */
    $sms = app(SmsSender::class);

    return $sms->lastTo('+989121112233')['code'];
}

beforeEach(function () {
    $this->vet = User::factory()->staff(Role::Vet)->create(['mobile' => '+989121112233']);
});

it('rejects a wrong password without sending a code', function () {
    staffLogin('wrong-password')->assertStatus(422);

    /** @var FakeSmsSender $sms */
    $sms = app(SmsSender::class);
    $sms->assertSentCount(0);
});

it('locks out after 5 wrong passwords', function () {
    foreach (range(1, 5) as $_) {
        staffLogin('wrong-password')->assertStatus(422);
    }

    staffLogin()->assertStatus(429);
});

it('requires password then SMS code and issues a staff token', function () {
    $challenge = staffLogin()->assertStatus(202)->json('challenge');

    $token = $this->postJson('/api/v1/auth/staff/verify', ['challenge' => $challenge, 'code' => staffCode()])
        ->assertOk()
        ->assertJsonPath('user.roles', ['vet'])
        ->json('token');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
});

it('rejects an unknown or reused challenge', function () {
    $challenge = staffLogin()->json('challenge');
    $code = staffCode();

    $this->postJson('/api/v1/auth/staff/verify', ['challenge' => str_repeat('x', 40), 'code' => $code])->assertStatus(422);
    $this->postJson('/api/v1/auth/staff/verify', ['challenge' => $challenge, 'code' => $code])->assertOk();
    $this->postJson('/api/v1/auth/staff/verify', ['challenge' => $challenge, 'code' => $code])->assertStatus(422);
});

it('does not let owners log in through the staff endpoint', function () {
    User::factory()->owner()->create(['mobile' => '+989129998877']);

    $this->postJson('/api/v1/auth/staff/login', ['mobile' => '09129998877', 'password' => 'anything'])->assertStatus(422);
});

describe('staff management', function () {
    function adminToken(): string
    {
        $admin = User::factory()->staff(Role::Admin)->create();

        return $admin->createToken('staff-spa', ['staff'])->plainTextToken;
    }

    it('lets an admin create staff with roles', function () {
        $this->withToken(adminToken())->postJson('/api/v1/admin/staff', [
            'mobile' => '09125554433',
            'name' => 'دكتر رضايي',
            'password' => 'StrongPass123',
            'roles' => ['vet', 'vaccinator'],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'دکتر رضایی')
            ->assertJsonPath('data.is_staff', true);

        $staff = User::where('mobile', '+989125554433')->sole();
        expect($staff->hasAllRoles(['vet', 'vaccinator']))->toBeTrue();
    });

    it('refuses non-staff roles and weak passwords', function () {
        $this->withToken(adminToken())->postJson('/api/v1/admin/staff', [
            'mobile' => '09125554433',
            'name' => 'Test',
            'password' => 'short',
            'roles' => ['owner'],
        ])->assertStatus(422)->assertJsonValidationErrors(['password', 'roles.0']);
    });

    it('forbids non-admin staff', function () {
        $token = $this->vet->createToken('staff-spa', ['staff'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/staff')->assertForbidden();
    });

    it('forbids owner tokens on admin routes', function () {
        $admin = User::factory()->staff(Role::Admin)->create();
        // An owner-ability token must not reach admin routes even for an admin account.
        $token = $admin->createToken('spa', ['owner'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/staff')->assertForbidden();
    });
});
