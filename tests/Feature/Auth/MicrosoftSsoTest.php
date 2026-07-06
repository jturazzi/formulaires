<?php

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

function fakeMicrosoftUser(string $id, string $email, string $name): void
{
    config(['services.microsoft.client_id' => 'fake-client-id']);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);
}

test('the first SSO user becomes an administrator', function () {
    fakeMicrosoftUser('azure-1', 'premier@anefloire.fr', 'Premier Utilisateur');

    $this->get('/auth/microsoft/callback')->assertRedirect(route('dashboard'));

    $user = User::first();

    expect($user->role)->toBe('admin')
        ->and($user->azure_id)->toBe('azure-1')
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('subsequent SSO users are creators', function () {
    User::factory()->create(['role' => 'admin']);

    fakeMicrosoftUser('azure-2', 'deuxieme@anefloire.fr', 'Deuxième Utilisateur');

    $this->get('/auth/microsoft/callback');

    expect(User::where('azure_id', 'azure-2')->first()->role)->toBe('creator');
});

test('an existing account is linked by email', function () {
    $existing = User::factory()->create(['email' => 'existant@anefloire.fr']);

    fakeMicrosoftUser('azure-3', 'existant@anefloire.fr', 'Existant');

    $this->get('/auth/microsoft/callback');

    expect(User::count())->toBe(1)
        ->and($existing->fresh()->azure_id)->toBe('azure-3');
});

test('the SSO routes return 404 when not configured', function () {
    config(['services.microsoft.client_id' => null]);

    $this->get('/auth/microsoft')->assertNotFound();
    $this->get('/auth/microsoft/callback')->assertNotFound();
});

test('an error returned by Microsoft redirects to login with a visible message', function () {
    config(['services.microsoft.client_id' => 'fake-client-id']);

    $this->get('/auth/microsoft/callback?error=invalid_request&error_description=tenant+mismatch')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(User::count())->toBe(0);
    $this->assertGuest();
});

test('password login, registration and password reset are disabled once SSO is configured', function () {
    config(['services.microsoft.client_id' => 'fake-client-id']);

    $this->get('/login')->assertOk();
    $this->post('/login', ['email' => 'a@b.com', 'password' => 'whatever'])->assertNotFound();
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password')->assertNotFound();
});
