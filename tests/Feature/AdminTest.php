<?php

use App\Models\Setting;
use App\Models\User;

test('non-admins cannot access admin pages', function () {
    $user = User::factory()->create(['role' => 'creator']);

    $this->actingAs($user)->get('/admin/settings')->assertForbidden();
    $this->actingAs($user)->get('/admin/users')->assertForbidden();
});

test('admins can update GDPR settings and legal pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->put('/admin/settings', [
        'default_retention_days' => 180,
        'terms_fr' => '# CGU',
        'terms_en' => '# Terms',
        'privacy_fr' => '# Confidentialité',
        'privacy_en' => '# Privacy',
    ])->assertRedirect();

    expect(Setting::get('default_retention_days'))->toBe('180')
        ->and(Setting::get('terms_fr'))->toBe('# CGU');
});

test('admins can change a user role', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'creator']);

    $this->actingAs($admin)->patch(route('admin.users.update', $user), ['role' => 'admin']);

    expect($user->fresh()->role)->toBe('admin');
});

test('an admin cannot change their own role', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->patch(route('admin.users.update', $admin), ['role' => 'creator']);

    expect($admin->fresh()->role)->toBe('admin');
});

test('legal pages are publicly accessible', function () {
    Setting::set('terms_fr', '# Mes CGU');
    Setting::set('privacy_fr', '# Ma politique');

    $this->get('/terms')->assertOk();
    $this->get('/privacy')->assertOk();
});
