<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormOwnershipController extends Controller
{
    public function update(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('transferOwnership', $form);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $newOwner = User::findOrCreateByEmail($validated['email']);

        if ($newOwner->id === $form->user_id) {
            return back()->with('error', __('messages.transfer_already_owner'));
        }

        DB::transaction(function () use ($form, $newOwner) {
            $previousOwnerId = $form->user_id;

            // user_id is deliberately not mass-assignable, so it's set directly here.
            $form->user_id = $newOwner->id;
            $form->save();

            // The new owner no longer needs a separate collaborator entry, and the
            // previous owner keeps their access as a collaborator instead of losing it.
            $form->shares()->where('user_id', $newOwner->id)->delete();
            $form->shares()->firstOrCreate(['user_id' => $previousOwnerId]);
        });

        return back()->with('success', __('messages.saved'));
    }
}
