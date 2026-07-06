<?php

use App\Models\Answer;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Response;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('the owner can list responses and export them as CSV', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();
    $field = FormField::factory()->for($form)->create(['label' => 'Nom']);
    $response = Response::factory()->for($form)->create();
    Answer::factory()->for($response)->create(['form_field_id' => $field->id, 'value' => 'Jean']);

    $this->actingAs($user)->get(route('forms.responses.index', $form))->assertOk();

    $csv = $this->actingAs($user)->get(route('forms.responses.export', $form));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('Nom')->toContain('Jean');
});

test('a stranger cannot see responses or download files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('form-uploads/1/secret.pdf', 'secret');

    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $form = Form::factory()->for($owner)->create();
    $response = Response::factory()->for($form)->create();
    $answer = Answer::factory()->for($response)->create([
        'form_field_id' => FormField::factory()->for($form)->type('file')->create()->id,
        'file_path' => 'form-uploads/1/secret.pdf',
        'file_name' => 'secret.pdf',
    ]);

    $this->get(route('answers.file', $answer))->assertRedirect(route('login'));

    $this->actingAs($stranger)->get(route('forms.responses.index', $form))->assertForbidden();
    $this->actingAs($stranger)->get(route('answers.file', $answer))->assertForbidden();
});

test('the owner can download an uploaded file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('form-uploads/1/piece.pdf', 'contenu');

    $owner = User::factory()->create();
    $form = Form::factory()->for($owner)->create();
    $response = Response::factory()->for($form)->create();
    $answer = Answer::factory()->for($response)->create([
        'form_field_id' => FormField::factory()->for($form)->type('file')->create()->id,
        'file_path' => 'form-uploads/1/piece.pdf',
        'file_name' => 'piece.pdf',
    ]);

    $this->actingAs($owner)->get(route('answers.file', $answer))->assertOk();
});
