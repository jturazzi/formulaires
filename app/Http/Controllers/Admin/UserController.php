<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class UserController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('admin/Users', [
            'users' => User::query()
                ->withCount('forms')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'forms_count' => $user->forms_count,
                    'sso' => $user->azure_id !== null,
                    'created_at' => $user->created_at,
                ]),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'creator'])],
        ]);

        // An admin cannot demote themselves: this guarantees at least one admin remains.
        if ($user->id === $request->user()->id) {
            return back()->with('error', __('messages.cannot_change_own_role'));
        }

        $user->update($validated);

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', __('messages.cannot_delete_self'));
        }

        // Deleting a user deletes their forms, responses and files (GDPR).
        $user->forms()->get()->each->delete();
        $user->delete();

        return back()->with('success', __('messages.user_deleted'));
    }
}
