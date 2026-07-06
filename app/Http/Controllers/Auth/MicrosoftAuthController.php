<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(config('services.microsoft.client_id'), 404);

        return Socialite::driver('microsoft')->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(config('services.microsoft.client_id'), 404);

        try {
            $microsoftUser = Socialite::driver('microsoft')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['email' => __('auth.sso_failed')]);
        }

        $email = $microsoftUser->getEmail();

        if (! $email) {
            return redirect()->route('login')->withErrors(['email' => __('auth.sso_failed')]);
        }

        $user = User::query()
            ->where('azure_id', $microsoftUser->getId())
            ->orWhere('email', $email)
            ->first();

        if (! $user) {
            $user = new User([
                'name' => $microsoftUser->getName() ?: $email,
                'email' => $email,
            ]);

            // The very first account becomes the administrator.
            $user->role = User::query()->exists() ? 'creator' : 'admin';
        }

        $user->azure_id = $microsoftUser->getId();
        $user->avatar = $microsoftUser->getAvatar();
        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
