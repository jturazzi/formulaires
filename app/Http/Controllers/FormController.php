<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormShare;
use App\Models\Setting;
use App\Models\User;
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
        $user = $request->user();

        $query = Form::query();

        // Admins have full visibility; everyone else only sees forms they own or were shared into.
        if (! $user->isAdmin()) {
            $query->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->orWhereHas('shares', fn ($shares) => $shares->where('user_id', $user->id))
            );
        }

        $forms = $query->with('user:id,name')
            ->withCount('responses')
            ->latest('updated_at')
            ->get();

        $sharedFormIds = $user->sharedForms()->pluck('forms.id')->all();

        return Inertia::render('forms/Index', [
            'forms' => $forms->map(fn (Form $form) => [
                'id' => $form->id,
                'title' => $form->title,
                'status' => $form->status,
                'slug' => $form->slug,
                'responses_count' => $form->responses_count,
                'expires_at' => $form->expires_at,
                'updated_at' => $form->updated_at,
                'is_owner' => $form->user_id === $user->id,
                'is_shared_with_me' => in_array($form->id, $sharedFormIds, true),
                'owner_name' => $form->user->name,
                'can_delete' => $user->isAdmin() || $form->user_id === $user->id,
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
            'form' => $this->formPayload($form, $request->user()),
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
            'slug' => [
                'required', 'string', 'min:3', 'max:32',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('forms', 'slug')->ignore($form->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'require_email_verification' => ['boolean'],
            'notify_on_response' => ['boolean'],
            'notification_emails' => ['nullable', 'array', 'max:10'],
            'notification_emails.*' => ['email'],
            'max_responses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'success_message' => ['nullable', 'string', 'max:2000'],
        ], [
            'slug.regex' => __('messages.slug_format'),
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

    private function formPayload(Form $form, User $user): array
    {
        $form->load(['sections.fields', 'user:id,name,email']);

        $isOwner = $form->user_id === $user->id;
        $canManageShares = $user->can('manageShares', $form);
        $canTransferOwnership = $user->can('transferOwnership', $form);

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
            'notification_emails' => $form->notification_emails ?? [],
            'max_responses' => $form->max_responses,
            'expires_at' => $form->expires_at?->format('Y-m-d\TH:i'),
            'retention_days' => $form->retention_days,
            'success_message' => $form->success_message,
            'public_url' => route('public.forms.show', $form->slug),
            'responses_count' => $form->responses()->count(),
            'is_owner' => $isOwner,
            'is_shared_with_me' => ! $isOwner && $form->shares()->where('user_id', $user->id)->exists(),
            'can_manage_shares' => $canManageShares,
            'can_transfer_ownership' => $canTransferOwnership,
            'owner' => [
                'name' => $form->user->name,
                'email' => $form->user->email,
            ],
            'shares' => $canManageShares
                ? $form->shares()->with('user:id,name,email,avatar')->get()->map(fn (FormShare $share) => [
                    'id' => $share->id,
                    'user' => [
                        'id' => $share->user->id,
                        'name' => $share->user->name,
                        'email' => $share->user->email,
                        'avatar' => $share->user->avatar,
                    ],
                ])
                : null,
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
