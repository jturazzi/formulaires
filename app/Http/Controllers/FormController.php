<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormField;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class FormController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $forms = $request->user()->forms()
            ->withCount('responses')
            ->latest('updated_at')
            ->get();

        return Inertia::render('forms/Index', [
            'forms' => $forms->map(fn (Form $form) => [
                'id' => $form->id,
                'title' => $form->title,
                'status' => $form->status,
                'slug' => $form->slug,
                'responses_count' => $form->responses_count,
                'expires_at' => $form->expires_at,
                'updated_at' => $form->updated_at,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $form = $request->user()->forms()->create($validated);
        $form->sections()->create(['position' => 0]);

        return redirect()->route('forms.edit', $form);
    }

    public function edit(Request $request, Form $form): InertiaResponse
    {
        $this->authorize('update', $form);

        return Inertia::render('forms/Edit', [
            'form' => $this->formPayload($form),
            'fieldTypes' => FormField::TYPES,
            'defaultRetentionDays' => (int) Setting::get('default_retention_days', config('formulaires.default_retention_days')),
            'maxUploadKb' => (int) config('formulaires.max_upload_kb'),
        ]);
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('update', $form);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'require_email_verification' => ['boolean'],
            'notify_on_response' => ['boolean'],
            'max_responses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'success_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $form->update($validated);

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('delete', $form);

        $form->delete();

        return redirect()->route('forms.index')->with('success', __('messages.form_deleted'));
    }

    public function updateStatus(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('update', $form);

        $validated = $request->validate([
            'status' => ['required', Rule::in([Form::STATUS_DRAFT, Form::STATUS_PUBLISHED, Form::STATUS_CLOSED])],
        ]);

        if ($validated['status'] === Form::STATUS_PUBLISHED && $form->fields()->where('type', '!=', 'info')->doesntExist()) {
            return back()->with('error', __('messages.publish_needs_fields'));
        }

        $form->status = $validated['status'];

        if ($validated['status'] === Form::STATUS_PUBLISHED) {
            $form->published_at ??= now();
        }

        $form->save();

        return back()->with('success', __('messages.saved'));
    }

    public function duplicate(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('view', $form);

        $copy = $form->replicate(['slug', 'status', 'published_at', 'logo_path']);
        $copy->title = $form->title.' ('.__('messages.copy').')';
        $copy->status = Form::STATUS_DRAFT;
        $copy->user_id = $request->user()->id;
        $copy->save();

        if ($form->logo_path) {
            $extension = pathinfo($form->logo_path, PATHINFO_EXTENSION);
            $newPath = 'logos/'.$copy->id.'-'.uniqid().'.'.$extension;

            if (Storage::disk('public')->copy($form->logo_path, $newPath)) {
                $copy->update(['logo_path' => $newPath]);
            }
        }

        foreach ($form->sections()->with('fields')->get() as $section) {
            $newSection = $copy->sections()->create($section->only(['title', 'description', 'position']));

            foreach ($section->fields as $field) {
                $copy->fields()->create([
                    ...$field->only(['type', 'label', 'description', 'required', 'options', 'position']),
                    'form_section_id' => $newSection->id,
                ]);
            }
        }

        return redirect()->route('forms.edit', $copy)->with('success', __('messages.form_duplicated'));
    }

    public function uploadLogo(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('update', $form);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        if ($form->logo_path) {
            Storage::disk('public')->delete($form->logo_path);
        }

        $form->update([
            'logo_path' => $request->file('logo')->store('logos', 'public'),
        ]);

        return back()->with('success', __('messages.saved'));
    }

    public function deleteLogo(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('update', $form);

        if ($form->logo_path) {
            Storage::disk('public')->delete($form->logo_path);
            $form->update(['logo_path' => null]);
        }

        return back()->with('success', __('messages.saved'));
    }

    private function formPayload(Form $form): array
    {
        $form->load(['sections.fields']);

        return [
            'id' => $form->id,
            'slug' => $form->slug,
            'title' => $form->title,
            'description' => $form->description,
            'logo_url' => $form->logo_path ? Storage::disk('public')->url($form->logo_path) : null,
            'primary_color' => $form->primary_color,
            'status' => $form->status,
            'require_email_verification' => $form->require_email_verification,
            'notify_on_response' => $form->notify_on_response,
            'max_responses' => $form->max_responses,
            'expires_at' => $form->expires_at?->format('Y-m-d\TH:i'),
            'retention_days' => $form->retention_days,
            'success_message' => $form->success_message,
            'public_url' => route('public.forms.show', $form->slug),
            'responses_count' => $form->responses()->count(),
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
                ]),
            ]),
        ];
    }
}
