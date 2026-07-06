<?php

namespace App\Http\Controllers;

use App\Mail\RespondentCodeMail;
use App\Models\Form;
use App\Models\RespondentVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class RespondentVerificationController extends Controller
{
    /**
     * Email a 6-digit verification code to a respondent.
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $form = Form::query()->where('slug', $slug)->firstOrFail();

        abort_unless($form->isOpen() && $form->require_email_verification, 404);

        $validated = $request->validate([
            'email' => ['required', 'email:filter', 'max:255'],
        ]);

        $code = (string) random_int(100000, 999999);

        RespondentVerification::query()
            ->where('form_id', $form->id)
            ->where('email', $validated['email'])
            ->delete();

        RespondentVerification::query()->create([
            'form_id' => $form->id,
            'email' => $validated['email'],
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(15),
        ]);

        Mail::to($validated['email'])->send(new RespondentCodeMail($form, $code));

        return back()->with('success', __('messages.code_sent'));
    }
}
