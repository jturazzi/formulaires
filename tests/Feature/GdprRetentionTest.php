<?php

use App\Models\Answer;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Response;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('the purge command deletes responses older than the form retention', function () {
    $form = Form::factory()->create(['retention_days' => 30]);
    $old = Response::factory()->for($form)->create(['submitted_at' => now()->subDays(31)]);
    $recent = Response::factory()->for($form)->create(['submitted_at' => now()->subDays(29)]);

    $this->artisan('responses:purge')->assertSuccessful();

    expect(Response::find($old->id))->toBeNull()
        ->and(Response::find($recent->id))->not->toBeNull();
});

test('the purge command uses the global default when the form has no retention', function () {
    Setting::set('default_retention_days', '10');

    $form = Form::factory()->create(['retention_days' => null]);
    $old = Response::factory()->for($form)->create(['submitted_at' => now()->subDays(11)]);

    $this->artisan('responses:purge')->assertSuccessful();

    expect(Response::find($old->id))->toBeNull();
});

test('purging a response deletes its uploaded files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('form-uploads/1/test.pdf', 'content');

    $form = Form::factory()->create(['retention_days' => 5]);
    $response = Response::factory()->for($form)->create(['submitted_at' => now()->subDays(6)]);
    Answer::factory()->for($response)->create([
        'form_field_id' => FormField::factory()->for($form)->type('file')->create()->id,
        'value' => null,
        'file_path' => 'form-uploads/1/test.pdf',
        'file_name' => 'test.pdf',
    ]);

    $this->artisan('responses:purge')->assertSuccessful();

    Storage::disk('local')->assertMissing('form-uploads/1/test.pdf');
});

test('dry run does not delete anything', function () {
    $form = Form::factory()->create(['retention_days' => 5]);
    Response::factory()->for($form)->create(['submitted_at' => now()->subDays(10)]);

    $this->artisan('responses:purge', ['--dry-run' => true])->assertSuccessful();

    expect(Response::count())->toBe(1);
});

test('deleting a form removes its responses and files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('form-uploads/1/piece.pdf', 'content');

    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();
    $response = Response::factory()->for($form)->create();
    Answer::factory()->for($response)->create([
        'form_field_id' => FormField::factory()->for($form)->create()->id,
        'file_path' => 'form-uploads/1/piece.pdf',
        'file_name' => 'piece.pdf',
    ]);

    $this->actingAs($user)->delete(route('forms.destroy', $form));

    expect(Form::count())->toBe(0)
        ->and(Response::count())->toBe(0);
    Storage::disk('local')->assertMissing('form-uploads/1/piece.pdf');
});

test('an owner can erase a single response', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();
    $response = Response::factory()->for($form)->create();

    $this->actingAs($user)->delete(route('forms.responses.destroy', [$form, $response]))->assertRedirect();

    expect(Response::count())->toBe(0);
});
