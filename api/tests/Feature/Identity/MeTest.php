<?php

use App\Models\User;
use App\Modules\Audit\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('requires authentication', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('returns the signed-in user with roles', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('spa', ['owner'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.roles', ['owner'])
        ->assertJsonMissingPath('data.national_code');
});

it('updates profile fields with Persian normalization and audits the change', function () {
    $user = User::factory()->owner()->create(['locale' => 'fa', 'name' => null]);
    $token = $user->createToken('spa', ['owner'])->plainTextToken;

    $this->withToken($token)->patchJson('/api/v1/me', [
        'name' => 'علي  كريمي',
        'locale' => 'en',
        'national_code' => '۱۲۳۴۵۶۷۸۹۱',
    ])->assertOk()
        ->assertJsonPath('data.name', 'علی کریمی')
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.national_code_masked', '******7891');

    $log = AuditLog::where('action', 'user.updated')->latest('id')->first();
    expect($log->before['locale'])->toBe('fa')
        ->and($log->after['locale'])->toBe('en');
});

it('rejects an invalid national code', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('spa', ['owner'])->plainTextToken;

    $this->withToken($token)->patchJson('/api/v1/me', ['national_code' => '1234567890'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('national_code');
});

it('answers in the user\'s saved language', function () {
    $user = User::factory()->owner()->create(['locale' => 'en']);
    $token = $user->createToken('spa', ['owner'])->plainTextToken;

    $this->withToken($token)->patchJson('/api/v1/me', ['national_code' => '1234567890'])
        ->assertJsonPath('errors.national_code.0', 'The national code is not valid.')
        ->assertHeader('Content-Language', 'en');
});

it('blocks suspended users even with a valid token', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('spa', ['owner'])->plainTextToken;
    $user->update(['status' => User::STATUS_SUSPENDED]);

    $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
});

it('revokes the token on logout', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('spa', ['owner'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('keeps the audit log append-only at the database level', function () {
    User::factory()->owner()->create();
    $id = AuditLog::query()->value('id');

    // Model guard first: the DB trigger below aborts the surrounding Postgres transaction.
    expect(fn () => AuditLog::find($id)->delete())->toThrow(LogicException::class);
    expect(fn () => DB::table('audit_logs')->where('id', $id)->update(['action' => 'tampered']))
        ->toThrow(QueryException::class, 'append-only');
});
