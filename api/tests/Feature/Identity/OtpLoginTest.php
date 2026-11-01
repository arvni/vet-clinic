<?php

use App\Models\User;
use App\Modules\Audit\AuditLog;
use App\Modules\Identity\Role;
use App\Modules\Identity\Sms\FakeSmsSender;
use App\Modules\Identity\Sms\SmsSender;
use App\Support\Digits;
use Illuminate\Testing\TestResponse;

function sms(): FakeSmsSender
{
    /** @var FakeSmsSender $sender */
    $sender = app(SmsSender::class);

    return $sender;
}

function requestCode(string $mobile = '09121234567', array $headers = []): TestResponse
{
    return test()->postJson('/api/v1/auth/otp/request', ['mobile' => $mobile], $headers);
}

function lastCode(string $e164 = '+989121234567'): string
{
    return sms()->lastTo($e164)['code'];
}

it('sends a 5-digit code to the normalized mobile', function () {
    requestCode('۰۹۱۲ ۱۲۳ ۴۵۶۷')
        ->assertStatus(202)
        ->assertJson(['resend_after' => 60, 'expires_in' => 120]);

    expect(lastCode())->toMatch('/^\d{5}$/');
    expect(sms()->sent[0]['template'])->toBe('vet-otp-fa');
});

it('rejects an invalid mobile', function () {
    requestCode('02112345678')->assertStatus(422)->assertJsonValidationErrors('mobile');
    sms()->assertSentCount(0);
});

it('enforces the resend cooldown', function () {
    requestCode()->assertStatus(202);
    requestCode()->assertStatus(429)->assertJsonPath('reason', 'cooldown');

    $this->travel(61)->seconds();
    requestCode()->assertStatus(202);
});

it('caps code requests per mobile', function () {
    foreach (range(1, 5) as $_) {
        requestCode()->assertStatus(202);
        $this->travel(61)->seconds();
    }

    requestCode()->assertStatus(429)->assertJsonPath('reason', 'too_many');
});

it('creates an owner account on first verify and returns a token', function () {
    requestCode();

    $response = $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => lastCode()])
        ->assertOk()
        ->assertJsonPath('new_account', true)
        ->assertJsonPath('user.mobile', '+989121234567')
        ->assertJsonPath('user.roles', [Role::Owner->value]);

    $user = User::where('mobile', '+989121234567')->sole();
    expect($user->mobile_verified_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'auth.login')->where('actor_id', $user->id)->exists())->toBeTrue();

    $this->withToken($response->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('signs an existing owner in without creating a duplicate', function () {
    User::factory()->owner()->create(['mobile' => '+989121234567']);
    requestCode();

    $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => lastCode()])
        ->assertOk()
        ->assertJsonPath('new_account', false);

    expect(User::count())->toBe(1);
});

it('accepts the code typed with Persian digits', function () {
    requestCode();
    $persian = Digits::toPersian(lastCode());

    $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => $persian])->assertOk();
});

it('rejects a wrong code and burns the code after 5 wrong attempts', function () {
    requestCode();
    $code = lastCode();
    $wrong = $code === '00000' ? '11111' : '00000';

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => $wrong])
            ->assertStatus(422)->assertJsonPath('reason', 'invalid');
    }

    $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => $code])->assertStatus(422);
});

it('expires the code after 2 minutes', function () {
    requestCode();
    $code = lastCode();

    $this->travel(121)->seconds();

    $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => $code])->assertStatus(422);
});

it('does not extend the code lifetime on wrong guesses', function () {
    requestCode();
    $code = lastCode();
    $wrong = $code === '00000' ? '11111' : '00000';

    $this->travel(100)->seconds();
    $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => $wrong])->assertStatus(422);
    $this->travel(25)->seconds();

    $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => $code])->assertStatus(422);
});

it('refuses OTP-only sign-in for staff', function () {
    User::factory()->staff(Role::Vet)->create(['mobile' => '+989121234567']);
    requestCode();

    $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => lastCode()])
        ->assertStatus(422);
});

it('refuses suspended owners', function () {
    User::factory()->owner()->suspended()->create(['mobile' => '+989121234567']);
    requestCode();

    $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => lastCode()])
        ->assertStatus(403);
});

it('uses English for a new user who browses in English', function () {
    requestCode('09121234567', ['Accept-Language' => 'en'])->assertStatus(202);
    expect(sms()->sent[0]['template'])->toBe('vet-otp-en');

    $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '09121234567', 'code' => lastCode()], ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('user.locale', 'en');
});

it('returns Persian error messages for Persian clients', function () {
    requestCode('123', ['Accept-Language' => 'fa'])->assertStatus(422)->assertJsonPath('errors.mobile.0', 'شماره موبایل معتبر نیست.');
});
