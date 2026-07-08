<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormShare;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormShareController extends Controller
{
    public function store(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('manageShares', $form);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::findOrCreateByEmail($validated['email']);

        if ($user->id === $form->user_id) {
            return back()->with('error', __('messages.share_is_owner'));
        }

        $form->shares()->firstOrCreate(['user_id' => $user->id]);

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Request $request, Form $form, FormShare $share): RedirectResponse
    {
        $this->authorize('manageShares', $form);

        abort_unless($share->form_id === $form->id, 404);

        $share->delete();

        return back()->with('success', __('messages.saved'));
    }
}
