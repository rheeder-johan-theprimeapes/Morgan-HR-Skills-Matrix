<?php

declare(strict_types=1);

use App\Models\AccountAccessToken;
use App\Models\AccountAudit;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

it('renders private native sign-in and setup forms without public registration', function (): void {
    $this->get('/login')->assertOk()->assertSee('Employee ID')->assertSee('name="_token"', false);
    $this->get('/account-setup')->assertOk()->assertSee('Setup code');
    $this->get('/register')->assertNotFound();
    $this->get('/dashboard')->assertRedirect('/login');
});

it('provisions a no-email employee who sets credentials once and signs in and out', function (): void {
    $administrator = User::factory()->accountAdministrator()->create();
    $this->actingAs($administrator)->withSession(['account_session_version' => $administrator->session_version]);

    $this->post('/accounts', ['employee_number' => 'prod-001', 'name' => 'Fictional Employee', 'email' => null])
        ->assertRedirect();
    $account = User::query()->where('login_id', 'PROD-001')->firstOrFail();
    expect($account->email)->toBeNull();
    expect($account->employee->employee_number)->toBe('PROD-001');
    expect($account->password)->toBeNull();

    $issued = $this->post('/accounts/'.$account->id.'/access');
    $issued->assertOk()->assertViewIs('accounts.access')->assertHeader('Referrer-Policy', 'no-referrer');
    expect($issued->headers->get('Cache-Control'))->toContain('no-store');
    $code = $issued->viewData('code');
    expect($code)->toBeString()->toHaveLength(64);
    expect(AccountAccessToken::query()->where('user_id', $account->id)->firstOrFail()->token_hash)
        ->toBe(hash('sha256', $code));
    expect(AccountAudit::query()->get()->toJson())->not->toContain($code);
    $this->post('/logout')->assertRedirect('/login');

    $this->post('/account-setup', [
        'login_id' => 'prod-001', 'code' => $code,
        'password' => 'Fictional passphrase 784!', 'password_confirmation' => 'Fictional passphrase 784!',
    ])->assertRedirect('/login');
    expect(Hash::check('Fictional passphrase 784!', $account->refresh()->password))->toBeTrue();
    expect(AccountAccessToken::query()->where('user_id', $account->id)->firstOrFail()->used_at)->not->toBeNull();
    $this->assertDatabaseHas('account_audits', ['subject_id' => $account->id, 'action' => 'access.used']);

    $this->post('/login', ['login_id' => 'prod-001', 'password' => 'Fictional passphrase 784!'])
        ->assertRedirect('/dashboard')->assertSessionHas('account_session_version', $account->session_version);
    $this->assertAuthenticatedAs($account);
    $this->get('/dashboard')->assertOk()->assertSee('Fictional Employee');
    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

it('returns the same generic error for unknown disabled and incorrect native credentials', function (string $loginId, string $password, bool $active): void {
    User::factory()->withEmployee()->create(['login_id' => 'EMP-001', 'is_active' => $active]);

    $this->from('/login')->post('/login', ['login_id' => $loginId, 'password' => $password])
        ->assertRedirect('/login')->assertSessionHasErrors(['login_id' => 'The sign-in details could not be verified.']);
    $this->assertGuest();
})->with([
    'unknown account' => ['UNKNOWN', 'password', true],
    'incorrect password' => ['EMP-001', 'incorrect-password', true],
    'disabled account' => ['EMP-001', 'password', false],
]);

it('refuses sign-in when the linked employee is retired', function (): void {
    $account = User::factory()->withEmployee()->create();
    $account->employee->forceFill(['is_active' => false])->save();

    $this->post('/login', ['login_id' => $account->login_id, 'password' => 'password'])
        ->assertSessionHasErrors('login_id');
    $this->assertGuest();
});

it('revokes existing sessions when the account is disabled', function (): void {
    $account = User::factory()->withEmployee()->create();
    $this->post('/login', ['login_id' => $account->login_id, 'password' => 'password'])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertOk();
    $account->forceFill(['is_active' => false])->save();

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('rejects a stale session version after recovery', function (): void {
    $account = User::factory()->withEmployee()->create();
    $this->post('/login', ['login_id' => $account->login_id, 'password' => 'password'])->assertRedirect('/dashboard');
    $account->increment('session_version');

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('forbids ordinary employees from provisioning or issuing recovery access', function (): void {
    $employee = User::factory()->withEmployee()->create();
    $other = User::factory()->withEmployee()->create();
    $this->actingAs($employee)->withSession(['account_session_version' => $employee->session_version]);

    $this->post('/accounts', ['employee_number' => 'NEW-001', 'name' => 'Fictional New'])->assertForbidden();
    $this->post('/accounts/'.$other->id.'/access')->assertForbidden();
    $this->assertDatabaseMissing('employees', ['employee_number' => 'NEW-001']);
    $this->assertDatabaseCount('account_access_tokens', 0);
});

it('does not let provisioning input grant account administration', function (): void {
    $administrator = User::factory()->accountAdministrator()->create();
    $this->actingAs($administrator)->withSession(['account_session_version' => $administrator->session_version]);

    $this->post('/accounts', [
        'employee_number' => 'EMP-002', 'name' => 'Fictional Employee',
        'can_administer_accounts' => true, 'is_active' => false, 'session_version' => 999,
    ])->assertRedirect();

    $created = User::query()->where('login_id', 'EMP-002')->firstOrFail();
    expect($created->can_administer_accounts)->toBeFalse();
    expect($created->is_active)->toBeTrue();
    expect($created->session_version)->toBe(1);
});

it('rejects duplicate employee identifiers without leaving partial people', function (): void {
    $administrator = User::factory()->accountAdministrator()->create();
    $this->actingAs($administrator)->withSession(['account_session_version' => $administrator->session_version]);
    $this->post('/accounts', ['employee_number' => 'EMP-003', 'name' => 'Fictional One'])->assertRedirect();
    $peopleBefore = Employee::query()->count();

    $this->post('/accounts', ['employee_number' => 'emp-003', 'name' => 'Fictional Duplicate'])
        ->assertSessionHasErrors('employee_number');
    expect(Employee::query()->count())->toBe($peopleBefore);
});

it('refuses expired used wrong and disabled setup access with a generic response', function (string $condition): void {
    $administrator = User::factory()->accountAdministrator()->create();
    $account = User::factory()->withEmployee()->pendingSetup()->create();
    $this->actingAs($administrator)->withSession(['account_session_version' => $administrator->session_version]);
    $code = $this->post('/accounts/'.$account->id.'/access')->viewData('code');
    $this->post('/logout');
    $token = AccountAccessToken::query()->where('user_id', $account->id)->firstOrFail();
    if ($condition === 'expired') {
        $token->forceFill(['expires_at' => now()->subSecond()])->save();
    } elseif ($condition === 'used') {
        $token->forceFill(['used_at' => now()])->save();
    } elseif ($condition === 'disabled') {
        $account->forceFill(['is_active' => false])->save();
    } else {
        $code = str_repeat('x', 64);
    }

    $response = $this->from('/account-setup')->post('/account-setup', [
        'login_id' => $account->login_id, 'code' => $code,
        'password' => 'Fictional passphrase 784!', 'password_confirmation' => 'Fictional passphrase 784!',
    ])->assertRedirect('/account-setup')->assertSessionHasErrors(['code' => 'Setup access is invalid or has expired. Contact HR.']);
    expect($account->refresh()->password)->toBeNull();
    $response->assertSessionMissing('_old_input.code')->assertSessionMissing('_old_input.password');
})->with(['expired', 'used', 'wrong', 'disabled']);

it('invalidates the previous code and credentials when recovery is reissued', function (): void {
    $administrator = User::factory()->accountAdministrator()->create();
    $account = User::factory()->withEmployee()->create();
    $this->actingAs($administrator)->withSession(['account_session_version' => $administrator->session_version]);
    $firstCode = $this->post('/accounts/'.$account->id.'/access')->viewData('code');
    $secondCode = $this->post('/accounts/'.$account->id.'/access')->viewData('code');
    expect($secondCode)->not->toBe($firstCode);
    expect($account->refresh()->password)->toBeNull();
    $this->assertDatabaseCount('account_access_tokens', 1);
    $this->post('/logout');

    $this->post('/account-setup', [
        'login_id' => $account->login_id, 'code' => $firstCode,
        'password' => 'Fictional passphrase 784!', 'password_confirmation' => 'Fictional passphrase 784!',
    ])->assertSessionHasErrors('code');
    $this->post('/account-setup', [
        'login_id' => $account->login_id, 'code' => $secondCode,
        'password' => 'Fictional passphrase 784!', 'password_confirmation' => 'Fictional passphrase 784!',
    ])->assertRedirect('/login');
    $this->post('/account-setup', [
        'login_id' => $account->login_id, 'code' => $secondCode,
        'password' => 'Another fictional passphrase!', 'password_confirmation' => 'Another fictional passphrase!',
    ])->assertSessionHasErrors('code');
});

it('rate limits repeated sign-in failures', function (): void {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', ['login_id' => 'UNKNOWN', 'password' => 'incorrect-password'])->assertSessionHasErrors('login_id');
    }

    $this->post('/login', ['login_id' => 'UNKNOWN', 'password' => 'incorrect-password'])->assertTooManyRequests();
});

it('bootstraps the first account administrator once through the console', function (): void {
    $this->artisan('app:bootstrap-account-admin', ['employee_number' => 'HR-BOOT', 'name' => 'Fictional HR'])
        ->assertSuccessful();
    $account = User::query()->where('login_id', 'HR-BOOT')->firstOrFail();
    expect($account->can_administer_accounts)->toBeTrue();
    expect($account->password)->toBeNull();
    $this->assertDatabaseHas('account_access_tokens', ['user_id' => $account->id]);

    $this->artisan('app:bootstrap-account-admin', ['employee_number' => 'HR-OTHER', 'name' => 'Fictional Other'])
        ->assertFailed();
    $this->assertDatabaseMissing('users', ['login_id' => 'HR-OTHER']);
});

it('refuses setup access at its exact expiry boundary', function (): void {
    $this->travelTo(now()->startOfSecond());
    $administrator = User::factory()->accountAdministrator()->create();
    $account = User::factory()->withEmployee()->pendingSetup()->create();
    $this->actingAs($administrator)->withSession(['account_session_version' => $administrator->session_version]);
    $code = $this->post('/accounts/'.$account->id.'/access')->viewData('code');
    AccountAccessToken::query()->where('user_id', $account->id)->update(['expires_at' => now()]);
    $this->post('/logout');

    $this->post('/account-setup', [
        'login_id' => $account->login_id, 'code' => $code,
        'password' => 'Fictional passphrase 784!', 'password_confirmation' => 'Fictional passphrase 784!',
    ])->assertSessionHasErrors('code');
    expect($account->refresh()->password)->toBeNull();
});
