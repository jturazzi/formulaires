<?php

use App\Mail\NewResponseMail;
use App\Mail\RespondentCodeMail;
use App\Models\Form;
use App\Models\FormField;
use App\Models\RespondentVerification;
use App\Models\Response;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function publishedForm(array $attributes = []): Form
{
    $form = Form::factory()->published()->create($attributes);
    $form->sections()->create(['position' => 0]);

    return $form;
}

test('a published form is publicly visible', function () {
    $form = publishedForm();

    $this->get("/f/{$form->slug}")->assertOk();
});

test('a draft form is not publicly visible', function () {
    $form = Form::factory()->create();

    $this->get("/f/{$form->slug}")->assertNotFound();
});

test('the owner can preview their own draft form', function () {
    $form = Form::factory()->create();

    $this->actingAs($form->user)
        ->get("/f/{$form->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('preview', true)->where('closed', false));
});

test('another authenticated user cannot preview someone else\'s draft form', function () {
    $form = Form::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get("/f/{$form->slug}")
        ->assertNotFound();
});

test('a visitor can submit a response with consent', function () {
    $form = publishedForm();
    $field = FormField::factory()->for($form)->required()->create([
        'form_section_id' => $form->sections->first()->id,
        'label' => 'Votre nom',
    ]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => 'Jean Dupont'],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    $response = Response::first();

    expect($response->consented_at)->not->toBeNull()
        ->and($response->answers->first()->value)->toBe('Jean Dupont');
});

test('consent is mandatory', function () {
    $form = publishedForm();
    FormField::factory()->for($form)->create(['form_section_id' => $form->sections->first()->id]);

    $this->post("/f/{$form->slug}", ['answers' => []])->assertSessionHasErrors('consent');

    expect(Response::count())->toBe(0);
});

test('required fields are validated', function () {
    $form = publishedForm();
    $field = FormField::factory()->for($form)->required()->create(['form_section_id' => $form->sections->first()->id]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => ''],
    ])->assertSessionHasErrors("answers.{$field->id}");
});

test('choice answers must belong to the configured choices', function () {
    $form = publishedForm();
    $field = FormField::factory()->for($form)->type('choice')->create(['form_section_id' => $form->sections->first()->id]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => 'Not an option'],
    ])->assertSessionHasErrors("answers.{$field->id}");
});

test('a free-text "other" choice answer is accepted when allowed', function () {
    $form = publishedForm();
    $field = FormField::factory()->for($form)->type('choice')->create([
        'form_section_id' => $form->sections->first()->id,
        'options' => ['choices' => ['Rouge', 'Vert'], 'allow_other' => true],
    ]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => 'Turquoise'],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    expect(Response::first()->answers->first()->value)->toBe('Turquoise');
});

test('number answers are validated against min and max', function () {
    $form = publishedForm();
    $field = FormField::factory()->for($form)->type('number')->create([
        'form_section_id' => $form->sections->first()->id,
        'options' => ['min' => 1, 'max' => 10],
    ]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => 42],
    ])->assertSessionHasErrors("answers.{$field->id}");
});

test('date answers are validated against min and max date', function () {
    $form = publishedForm();
    $field = FormField::factory()->for($form)->type('date')->create([
        'form_section_id' => $form->sections->first()->id,
        'options' => ['min_date' => '2026-01-01', 'max_date' => '2026-12-31'],
    ]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => '2027-01-01'],
    ])->assertSessionHasErrors("answers.{$field->id}");
});

test('a phone answer is accepted and free-form text is rejected', function () {
    $form = publishedForm();
    $field = FormField::factory()->for($form)->type('phone')->required()->create([
        'form_section_id' => $form->sections->first()->id,
    ]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => '+33 6 12 34 56 78'],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    expect(Response::first()->answers()->first()->value)->toBe('+33 6 12 34 56 78');

    $this->withSession(['locale' => 'fr'])->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => 'not a phone number'],
    ])->assertSessionHasErrors([
        "answers.{$field->id}" => 'Le numéro de téléphone ne doit contenir que des chiffres, espaces et les caractères + - ( ).',
    ]);
});

test('a time answer must be a valid HH:MM time', function () {
    $form = publishedForm();
    $field = FormField::factory()->for($form)->type('time')->required()->create([
        'form_section_id' => $form->sections->first()->id,
    ]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => '14:30'],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    expect(Response::first()->answers()->first()->value)->toBe('14:30');

    $this->withSession(['locale' => 'fr'])->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => '25:99'],
    ])->assertSessionHasErrors([
        "answers.{$field->id}" => "L'heure doit être au format HH:MM (par exemple 14:30).",
    ]);
});

test('a star rating answer must be between 1 and 6', function () {
    $form = publishedForm();
    $field = FormField::factory()->for($form)->type('rating_star')->required()->create([
        'form_section_id' => $form->sections->first()->id,
    ]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => 6],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    expect(Response::first()->answers()->first()->value)->toBe(6);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => 7],
    ])->assertSessionHasErrors("answers.{$field->id}");
});

test('a 0-to-10 rating answer must be between 0 and 10', function () {
    $form = publishedForm();
    $field = FormField::factory()->for($form)->type('rating_number')->required()->create([
        'form_section_id' => $form->sections->first()->id,
    ]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => 7],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    expect(Response::first()->answers()->first()->value)->toBe(7);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => 0],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => 11],
    ])->assertSessionHasErrors("answers.{$field->id}");

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => -1],
    ])->assertSessionHasErrors("answers.{$field->id}");
});

test('a required field hidden by its visibility condition is not enforced', function () {
    $form = publishedForm();
    $section = $form->sections->first();

    $trigger = FormField::factory()->for($form)->type('choice')->create([
        'form_section_id' => $section->id,
        'options' => ['choices' => ['Oui', 'Non']],
    ]);

    $dependent = FormField::factory()->for($form)->required()->create([
        'form_section_id' => $section->id,
        'visibility' => [
            'mode' => 'visible_if',
            'logic' => 'all',
            'conditions' => [['field_id' => $trigger->id, 'operator' => 'equals', 'value' => 'Oui']],
        ],
    ]);

    // The trigger answer doesn't satisfy the condition, so the dependent field stays
    // hidden and its required rule should not block submission.
    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$trigger->id => 'Non'],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    expect(Response::first()->answers()->where('form_field_id', $dependent->id)->exists())->toBeFalse();
});

test('a value submitted for a hidden field is not stored', function () {
    $form = publishedForm();
    $section = $form->sections->first();

    $trigger = FormField::factory()->for($form)->type('choice')->create([
        'form_section_id' => $section->id,
        'options' => ['choices' => ['Oui', 'Non']],
    ]);

    $dependent = FormField::factory()->for($form)->create([
        'form_section_id' => $section->id,
        'visibility' => [
            'mode' => 'visible_if',
            'logic' => 'all',
            'conditions' => [['field_id' => $trigger->id, 'operator' => 'equals', 'value' => 'Oui']],
        ],
    ]);

    // Simulate a tampered request: the trigger says "Non" (dependent should stay
    // hidden) yet an answer is still supplied for the dependent field.
    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$trigger->id => 'Non', $dependent->id => 'Sneaked in'],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    expect(Response::first()->answers()->where('form_field_id', $dependent->id)->exists())->toBeFalse();
});

test('a visible field required by its condition is enforced', function () {
    $form = publishedForm();
    $section = $form->sections->first();

    $trigger = FormField::factory()->for($form)->type('choice')->create([
        'form_section_id' => $section->id,
        'options' => ['choices' => ['Oui', 'Non']],
    ]);

    $dependent = FormField::factory()->for($form)->required()->create([
        'form_section_id' => $section->id,
        'visibility' => [
            'mode' => 'visible_if',
            'logic' => 'all',
            'conditions' => [['field_id' => $trigger->id, 'operator' => 'equals', 'value' => 'Oui']],
        ],
    ]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$trigger->id => 'Oui'],
    ])->assertSessionHasErrors("answers.{$dependent->id}");
});

test('a visitor can upload a file', function () {
    Storage::fake('local');

    $form = publishedForm();
    $field = FormField::factory()->for($form)->type('file')->create(['form_section_id' => $form->sections->first()->id]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => UploadedFile::fake()->create('document.pdf', 100)],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    $answer = Response::first()->answers->first();

    expect($answer->file_name)->toBe('document.pdf');
    Storage::disk('local')->assertExists($answer->file_path);
});

test('disallowed file extensions are rejected', function () {
    Storage::fake('local');

    $form = publishedForm();
    $field = FormField::factory()->for($form)->type('file')->create(['form_section_id' => $form->sections->first()->id]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [$field->id => UploadedFile::fake()->create('malware.exe', 100)],
    ])->assertSessionHasErrors("answers.{$field->id}");
});

test('a closed form rejects submissions', function () {
    $form = publishedForm(['status' => Form::STATUS_CLOSED]);

    $this->post("/f/{$form->slug}", ['consent' => true])->assertNotFound();
});

test('an expired form rejects submissions', function () {
    $form = publishedForm(['expires_at' => now()->subHour()]);

    $this->post("/f/{$form->slug}", ['consent' => true])->assertNotFound();
});

test('the response limit is enforced', function () {
    $form = publishedForm(['max_responses' => 1]);
    Response::factory()->for($form)->create();

    $this->post("/f/{$form->slug}", ['consent' => true])->assertNotFound();
});

test('email verification is required when enabled', function () {
    $form = publishedForm(['require_email_verification' => true]);
    FormField::factory()->for($form)->create(['form_section_id' => $form->sections->first()->id]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'answers' => [],
    ])->assertSessionHasErrors(['email', 'code']);
});

test('a respondent can verify their email and submit', function () {
    Mail::fake();

    $form = publishedForm(['require_email_verification' => true]);
    $field = FormField::factory()->for($form)->create(['form_section_id' => $form->sections->first()->id]);

    $this->post("/f/{$form->slug}/email-code", ['email' => 'visiteur@example.com'])->assertRedirect();

    Mail::assertSent(RespondentCodeMail::class, fn (RespondentCodeMail $mail) => $mail->hasTo('visiteur@example.com'));

    // Replace the stored hash with a known code for the assertion.
    RespondentVerification::query()->update(['code_hash' => hash('sha256', '123456')]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'email' => 'visiteur@example.com',
        'code' => '123456',
        'answers' => [$field->id => 'Bonjour'],
    ])->assertRedirect(route('public.forms.thanks', $form->slug));

    $response = Response::first();

    expect($response->email)->toBe('visiteur@example.com')
        ->and($response->email_verified_at)->not->toBeNull();
});

test('a wrong verification code is rejected', function () {
    $form = publishedForm(['require_email_verification' => true]);

    RespondentVerification::query()->create([
        'form_id' => $form->id,
        'email' => 'visiteur@example.com',
        'code_hash' => hash('sha256', '123456'),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->post("/f/{$form->slug}", [
        'consent' => true,
        'email' => 'visiteur@example.com',
        'code' => '654321',
    ])->assertSessionHasErrors('code');
});

test('the owner is not notified unless they added themselves as a recipient', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $form = Form::factory()->for($owner)->published()->create(['notify_on_response' => true]);
    $form->sections()->create(['position' => 0]);
    FormField::factory()->for($form)->create(['form_section_id' => $form->sections()->first()->id]);

    $this->post("/f/{$form->slug}", ['consent' => true, 'answers' => []]);

    Mail::assertNothingSent();
});

test('notification recipients receive the new response email', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $form = Form::factory()->for($owner)->published()->create([
        'notify_on_response' => true,
        'notification_emails' => ['team@example.com', 'other@example.com'],
    ]);
    $form->sections()->create(['position' => 0]);
    FormField::factory()->for($form)->create(['form_section_id' => $form->sections()->first()->id]);

    $this->post("/f/{$form->slug}", ['consent' => true, 'answers' => []]);

    Mail::assertSent(NewResponseMail::class, fn (NewResponseMail $mail) => $mail->hasTo('team@example.com'));
    Mail::assertSent(NewResponseMail::class, fn (NewResponseMail $mail) => $mail->hasTo('other@example.com'));
    Mail::assertSent(NewResponseMail::class, 2);
});
