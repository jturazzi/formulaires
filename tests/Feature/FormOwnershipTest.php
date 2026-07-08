<?php

use App\Models\Form;
use App\Models\User;

test('the owner can transfer a form to another existing user', function () {
    $owner = User::factory()->create();
    $newOwner = User::factory()->create();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('forms.owner.update', $form), ['email' => $newOwner->email])
        ->assertRedirect();

    $form->refresh();

    expect($form->user_id)->toBe($newOwner->id)
        ->and($form->collaborators->pluck('id'))->toContain($owner->id)
        ->and($form->collaborators->pluck('id'))->not->toContain($newOwner->id);
});

test('transferring to an email with no account creates a placeholder user', function () {
    $owner = User::factory()->create();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('forms.owner.update', $form), ['email' => 'newowner@example.com'])
        ->assertRedirect();

    $newOwner = User::where('email', 'newowner@example.com')->firstOrFail();

    expect($form->fresh()->user_id)->toBe($newOwner->id);
});

test('transferring to an existing collaborator drops their now-redundant share', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $form = Form::factory()->for($owner)->create();
    $form->shares()->create(['user_id' => $collaborator->id]);

    $this->actingAs($owner)
        ->post(route('forms.owner.update', $form), ['email' => $collaborator->email])
        ->assertRedirect();

    $form->refresh();

    expect($form->user_id)->toBe($collaborator->id)
        ->and($form->collaborators->pluck('id'))->toContain($owner->id)
        ->and($form->collaborators->pluck('id'))->not->toContain($collaborator->id);
});

test('a collaborator cannot transfer ownership, only the owner or an admin can', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $someoneElse = User::factory()->create();
    $form = Form::factory()->for($owner)->create();
    $form->shares()->create(['user_id' => $collaborator->id]);

    $this->actingAs($collaborator)
        ->post(route('forms.owner.update', $form), ['email' => $someoneElse->email])
        ->assertForbidden();

    expect($form->fresh()->user_id)->toBe($owner->id);
});

test('an admin can transfer ownership of any form', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $newOwner = User::factory()->create();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($admin)
        ->post(route('forms.owner.update', $form), ['email' => $newOwner->email])
        ->assertRedirect();

    expect($form->fresh()->user_id)->toBe($newOwner->id);
});

test('transferring to the current owner is rejected', function () {
    $owner = User::factory()->create();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('forms.owner.update', $form), ['email' => $owner->email])
        ->assertSessionHas('error');

    expect($form->fresh()->user_id)->toBe($owner->id);
});
