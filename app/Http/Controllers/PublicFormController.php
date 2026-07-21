<?php

namespace App\Http\Controllers;

use App\Mail\NewResponseMail;
use App\Models\Form;
use App\Models\RespondentVerification;
use App\Services\FormSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PublicFormController extends Controller
{
    public function show(string $slug): InertiaResponse
    {
        $form = Form::query()->where('slug', $slug)->firstOrFail();

        abort_if($form->status === Form::STATUS_DRAFT, 404);

        return Inertia::render('public/Form', [
            'form' => $this->publicPayload($form),
            'closed' => ! $form->isOpen(),
        ]);
    }

    public function submit(Request $request, string $slug, FormSubmissionService $service): RedirectResponse
    {
        $form = Form::query()->where('slug', $slug)->firstOrFail();

        abort_unless($form->isOpen(), 404);

        $form->load('fields');

        $validated = $request->validate($service->rules($form, $request), [], $service->attributes($form));

        if ($form->require_email_verification) {
            $this->assertVerifiedCode($form, $validated['email'], $validated['code']);
        }

        $response = $service->store($form, $request, $validated);

        if ($form->notify_on_response) {
            foreach ($form->notificationRecipients() as $email) {
                Mail::to($email)->send(new NewResponseMail($form, $response));
            }
        }

        return redirect()->route('public.forms.thanks', $form->slug);
    }

    public function thanks(string $slug): InertiaResponse
    {
        $form = Form::query()->where('slug', $slug)->firstOrFail();

        abort_if($form->status === Form::STATUS_DRAFT, 404);

        return Inertia::render('public/Thanks', [
            'form' => [
                'title' => $form->title,
                'logo_url' => $form->logo_path ? Storage::disk('public')->url($form->logo_path) : null,
                'primary_color' => $form->primary_color,
                'success_message' => $form->success_message,
            ],
        ]);
    }

    private function assertVerifiedCode(Form $form, string $email, string $code): void
    {
        $verification = RespondentVerification::query()
            ->where('form_id', $form->id)
            ->where('email', $email)
            ->latest()
            ->first();

        if (! $verification || ! $verification->matches($code)) {
            throw ValidationException::withMessages([
                'code' => __('messages.invalid_code'),
            ]);
        }

        $verification->delete();
    }

    private function publicPayload(Form $form): array
    {
        $form->load('sections.fields');

        return [
            'slug' => $form->slug,
            'title' => $form->title,
            'description' => $form->description,
            'logo_url' => $form->logo_path ? Storage::disk('public')->url($form->logo_path) : null,
            'primary_color' => $form->primary_color,
            'require_email_verification' => $form->require_email_verification,
            'retention_days' => $form->effectiveRetentionDays(),
            'sections' => $form->sections->map(fn ($section) => [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'fields' => $section->fields->map(fn ($field) => [
                    'id' => $field->id,
                    'type' => $field->type,
                    'label' => $field->label,
                    'description' => $field->description,
                    'required' => $field->required,
                    'options' => $field->options,
                    'visibility' => $field->visibility,
                ]),
            ]),
        ];
    }
}
