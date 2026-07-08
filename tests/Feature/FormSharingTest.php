<?php

use App\Models\Form;
use App\Models\User;

test('the owner can share a form with another user by email', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('forms.shares.store', $form), ['email' => $collaborator->email])
        ->assertRedirect();

    expect($form->fresh()->collaborators->pluck('id'))->toContain($collaborator->id);
});

test('sharing with an email with no account creates a placeholder user', function () {
    $owner = User::factory()->create();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('forms.shares.store', $form), ['email' => 'nobody@example.com'])
        ->assertRedirect();

    $newUser = User::where('email', 'nobody@example.com')->first();

    expect($newUser)->not->toBeNull()
        ->and($newUser->role)->toBe('creator')
        ->and($form->fresh()->collaborators->pluck('id'))->toContain($newUser->id);
});

test('a collaborator can view and edit a shared form but cannot delete it', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $form = Form::factory()->for($owner)->create();
    $form->shares()->create(['user_id' => $collaborator->id]);

    $this->actingAs($collaborator)->get(route('forms.edit', $form))->assertOk();
    $this->actingAs($collaborator)->get(route('forms.responses.index', $form))->assertOk();
    $this->actingAs($collaborator)
        ->put(route('forms.update', $form), ['title' => 'Updated by collaborator', 'slug' => $form->slug])
        ->assertRedirect();
    $this->actingAs($collaborator)->delete(route('forms.destroy', $form))->assertForbidden();

    expect($form->fresh()->title)->toBe('Updated by collaborator');
});

test('a collaborator can also add and remove other collaborators', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $other = User::factory()->create();
    $form = Form::factory()->for($owner)->create();
    $form->shares()->create(['user_id' => $collaborator->id]);

    $this->actingAs($collaborator)
        ->post(route('forms.shares.store', $form), ['email' => $other->email])
        ->assertRedirect();

    expect($form->fresh()->collaborators->pluck('id'))->toContain($other->id);

    $share = $form->shares()->where('user_id', $other->id)->firstOrFail();

    $this->actingAs($collaborator)->delete(route('forms.shares.destroy', [$form, $share]))->assertRedirect();

    expect($form->fresh()->collaborators->pluck('id'))->not->toContain($other->id);
});

test('a stranger cannot manage shares on a form they have no access to', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $other = User::factory()->create();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($stranger)
        ->post(route('forms.shares.store', $form), ['email' => $other->email])
        ->assertForbidden();
});

test('an admin can see and manage every form, shared or not', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $collaborator = User::factory()->create();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($admin)
        ->post(route('forms.shares.store', $form), ['email' => $collaborator->email])
        ->assertRedirect();

    expect($form->fresh()->collaborators->pluck('id'))->toContain($collaborator->id);

    $response = $this->actingAs($admin)->get(route('forms.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('forms.0.id', $form->id)->where('forms.0.can_delete', true));
});

test('a user with no access cannot view a form', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($stranger)->get(route('forms.edit', $form))->assertForbidden();
});

test('the owner can remove a collaborator', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $form = Form::factory()->for($owner)->create();
    $share = $form->shares()->create(['user_id' => $collaborator->id]);

    $this->actingAs($owner)->delete(route('forms.shares.destroy', [$form, $share]))->assertRedirect();

    expect($form->fresh()->collaborators)->toHaveCount(0);
    $this->actingAs($collaborator)->get(route('forms.edit', $form))->assertForbidden();
});

test('shared forms appear in the collaborator\'s form list', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $form = Form::factory()->for($owner)->create();
    $form->shares()->create(['user_id' => $collaborator->id]);

    $this->actingAs($collaborator)->get(route('forms.index'))->assertOk();
});
