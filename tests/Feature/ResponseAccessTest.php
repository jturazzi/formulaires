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

test('the responses summary aggregates answers per question', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();
    $choice = FormField::factory()->for($form)->type('choice')->create(['options' => ['choices' => ['Oui', 'Non'], 'allow_other' => true], 'position' => 0]);
    $rating = FormField::factory()->for($form)->type('rating_star')->create(['position' => 1]);
    $text = FormField::factory()->for($form)->create(['position' => 2]);

    foreach ([['Oui', 4, 'A'], ['Oui', 6, null], ['Peut-être', 5, 'C']] as [$c, $r, $t]) {
        $response = Response::factory()->for($form)->create(['submitted_at' => now()]);
        Answer::factory()->for($response)->create(['form_field_id' => $choice->id, 'value' => $c]);
        Answer::factory()->for($response)->create(['form_field_id' => $rating->id, 'value' => $r]);

        if ($t) {
            Answer::factory()->for($response)->create(['form_field_id' => $text->id, 'value' => $t]);
        }
    }

    $this->actingAs($user)
        ->get(route('forms.responses.index', $form))
        ->assertInertia(fn ($page) => $page
            ->where('view', 'summary')
            ->where('summary.total', 3)
            ->where('summary.last_7_days', 3)
            ->has('summary.timeline', 30)
            ->where('summary.timeline.29.count', 3)
            ->where('summary.fields.0.kind', 'choices')
            ->where('summary.fields.0.options', [
                ['label' => 'Oui', 'count' => 2, 'other' => false],
                ['label' => 'Non', 'count' => 0, 'other' => false],
                ['label' => null, 'count' => 1, 'other' => true],
            ])
            ->where('summary.fields.0.samples', ['Peut-être'])
            ->where('summary.fields.1.kind', 'rating')
            ->where('summary.fields.1.average', 5)
            ->where('summary.fields.1.options.3.count', 1)
            ->where('summary.fields.2.answered', 2)
            ->where('summary.fields.2.samples', ['C', 'A']));

    // The individual list view skips the (costlier) aggregation.
    $this->actingAs($user)
        ->get(route('forms.responses.index', [$form, 'view' => 'list']))
        ->assertInertia(fn ($page) => $page->where('view', 'list')->where('summary', null));
});
