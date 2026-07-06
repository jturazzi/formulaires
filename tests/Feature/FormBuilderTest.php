<?php

use App\Models\Form;
use App\Models\FormField;
use App\Models\User;

test('a user can create a form with a default section', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/forms', ['title' => 'Inscription 2026']);

    $form = Form::first();

    $response->assertRedirect(route('forms.edit', $form));
    expect($form->title)->toBe('Inscription 2026')
        ->and($form->status)->toBe(Form::STATUS_DRAFT)
        ->and($form->sections)->toHaveCount(1)
        ->and($form->slug)->toHaveLength(16);
});

test('the builder can replace the whole structure', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();
    $section = $form->sections()->create(['position' => 0]);
    $existingField = $form->fields()->create([
        'form_section_id' => $section->id,
        'type' => 'text',
        'label' => 'Old label',
        'position' => 0,
    ]);

    $this->actingAs($user)
        ->put(route('forms.structure.update', $form), [
            'sections' => [
                [
                    'id' => $section->id,
                    'title' => 'Identité',
                    'fields' => [
                        ['id' => $existingField->id, 'type' => 'text', 'label' => 'Nom complet', 'required' => true],
                        ['id' => null, 'type' => 'choice', 'label' => 'Statut', 'options' => ['choices' => ['Salarié', 'Bénévole']]],
                    ],
                ],
                [
                    'id' => null,
                    'title' => 'Documents',
                    'fields' => [
                        ['id' => null, 'type' => 'file', 'label' => 'Pièce jointe'],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $form->refresh();

    expect($form->sections)->toHaveCount(2)
        ->and($form->fields)->toHaveCount(3)
        ->and($existingField->fresh()->label)->toBe('Nom complet')
        ->and($existingField->fresh()->required)->toBeTrue();
});

test('removed fields are deleted from the structure', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();
    $section = $form->sections()->create(['position' => 0]);
    $field = FormField::factory()->for($form)->create(['form_section_id' => $section->id]);

    $this->actingAs($user)->put(route('forms.structure.update', $form), [
        'sections' => [
            ['id' => $section->id, 'title' => null, 'fields' => []],
        ],
    ]);

    expect(FormField::find($field->id))->toBeNull();
});

test('a form cannot be published without at least one question', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();

    $this->actingAs($user)->post(route('forms.status.update', $form), ['status' => 'published']);

    expect($form->fresh()->status)->toBe(Form::STATUS_DRAFT);
});

test('publishing sets the published_at timestamp', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();
    $section = $form->sections()->create(['position' => 0]);
    FormField::factory()->for($form)->create(['form_section_id' => $section->id]);

    $this->actingAs($user)->post(route('forms.status.update', $form), ['status' => 'published']);

    expect($form->fresh()->status)->toBe(Form::STATUS_PUBLISHED)
        ->and($form->fresh()->published_at)->not->toBeNull();
});

test('a user cannot edit another user\'s form', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($intruder)->get(route('forms.edit', $form))->assertForbidden();
    $this->actingAs($intruder)->put(route('forms.update', $form), ['title' => 'Hacked'])->assertForbidden();
    $this->actingAs($intruder)->delete(route('forms.destroy', $form))->assertForbidden();
});

test('an admin can edit any form', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($admin)->get(route('forms.edit', $form))->assertOk();
});

test('the owner can customize the public slug', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();

    $this->actingAs($user)->put(route('forms.update', $form), [
        'title' => $form->title,
        'slug' => 'inscription-gala-2026',
    ])->assertRedirect();

    expect($form->fresh()->slug)->toBe('inscription-gala-2026');
});

test('the slug must be unique across forms', function () {
    $user = User::factory()->create();
    Form::factory()->for($user)->create(['slug' => 'deja-pris']);
    $form = Form::factory()->for($user)->create();

    $this->actingAs($user)->put(route('forms.update', $form), [
        'title' => $form->title,
        'slug' => 'deja-pris',
    ])->assertSessionHasErrors('slug');
});

test('the slug must only contain lowercase letters, numbers and hyphens', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();

    foreach (['Majuscules', 'espace ici', 'accentué', '-commence-par-tiret', 'finit-par-tiret-'] as $invalidSlug) {
        $this->actingAs($user)->put(route('forms.update', $form), [
            'title' => $form->title,
            'slug' => $invalidSlug,
        ])->assertSessionHasErrors('slug');
    }
});

test('duplicating a form copies its structure as a draft', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->published()->create();
    $section = $form->sections()->create(['title' => 'Section A', 'position' => 0]);
    FormField::factory()->for($form)->count(2)->create(['form_section_id' => $section->id]);

    $this->actingAs($user)->post(route('forms.duplicate', $form));

    $copy = Form::whereKeyNot($form->id)->first();

    expect($copy)->not->toBeNull()
        ->and($copy->status)->toBe(Form::STATUS_DRAFT)
        ->and($copy->slug)->not->toBe($form->slug)
        ->and($copy->fields)->toHaveCount(2)
        ->and($copy->sections)->toHaveCount(1);
});
