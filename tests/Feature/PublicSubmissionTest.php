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
