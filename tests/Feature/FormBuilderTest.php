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

test('the builder can save max length, min/max and date range options', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();
    $section = $form->sections()->create(['position' => 0]);

    $this->actingAs($user)
        ->put(route('forms.structure.update', $form), [
            'sections' => [
                [
                    'id' => $section->id,
                    'fields' => [
                        ['id' => null, 'type' => 'textarea', 'label' => 'Message', 'options' => ['max_length' => 500]],
                        ['id' => null, 'type' => 'number', 'label' => 'Âge', 'options' => ['min' => 18, 'max' => 99]],
                        ['id' => null, 'type' => 'date', 'label' => 'Naissance', 'options' => ['min_date' => '2000-01-01', 'max_date' => '2010-12-31']],
                        ['id' => null, 'type' => 'choice', 'label' => 'Couleur', 'options' => ['choices' => ['Rouge'], 'allow_other' => true]],
                    ],
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $fields = $form->fresh()->fields()->orderBy('id')->get();

    expect($fields[0]->options)->toBe(['max_length' => 500])
        ->and($fields[1]->options)->toBe(['min' => 18, 'max' => 99])
        ->and($fields[2]->options)->toBe(['min_date' => '2000-01-01', 'max_date' => '2010-12-31'])
        ->and($fields[3]->options)->toBe(['choices' => ['Rouge'], 'allow_other' => true]);
});

test('a number field max option must be greater than or equal to its min option', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();
    $section = $form->sections()->create(['position' => 0]);

    $this->actingAs($user)
        ->put(route('forms.structure.update', $form), [
            'sections' => [
                [
                    'id' => $section->id,
                    'fields' => [
                        ['id' => null, 'type' => 'number', 'label' => 'Âge', 'options' => ['min' => 50, 'max' => 10]],
                    ],
                ],
            ],
        ])
        ->assertSessionHasErrors('sections.0.fields.0.options.max');
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

test('notification recipients can be saved and are validated as emails', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();

    $this->actingAs($user)->put(route('forms.update', $form), [
        'title' => $form->title,
        'slug' => $form->slug,
        'notification_emails' => ['not-an-email'],
    ])->assertSessionHasErrors('notification_emails.0');

    $this->actingAs($user)->put(route('forms.update', $form), [
        'title' => $form->title,
        'slug' => $form->slug,
        'notification_emails' => ['a@example.com', 'b@example.com'],
    ])->assertRedirect();

    expect($form->fresh()->notification_emails)->toBe(['a@example.com', 'b@example.com']);
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

test('a logo upload is persisted on the form and shown in its public url', function () {
    Illuminate\Support\Facades\Storage::fake('public');

    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();

    $this->actingAs($user)->post(route('forms.logo.upload', $form), [
        'logo' => Illuminate\Http\UploadedFile::fake()->image('logo.png'),
    ])->assertRedirect();

    $form->refresh();

    expect($form->logo_path)->not->toBeNull();
    Illuminate\Support\Facades\Storage::disk('public')->assertExists($form->logo_path);
});

test('removing a logo clears it from the form and disk', function () {
    Illuminate\Support\Facades\Storage::fake('public');

    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();

    $this->actingAs($user)->post(route('forms.logo.upload', $form), [
        'logo' => Illuminate\Http\UploadedFile::fake()->image('logo.png'),
    ]);

    $path = $form->fresh()->logo_path;

    $this->actingAs($user)->delete(route('forms.logo.delete', $form))->assertRedirect();

    expect($form->fresh()->logo_path)->toBeNull();
    Illuminate\Support\Facades\Storage::disk('public')->assertMissing($path);
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
