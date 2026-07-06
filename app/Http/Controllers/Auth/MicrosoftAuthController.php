<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(config('services.microsoft.client_id'), 404);

        return Socialite::driver('microsoft')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(config('services.microsoft.client_id'), 404);

        if ($request->filled('error')) {
            Log::warning('Microsoft SSO callback returned an error', [
                'error' => $request->string('error')->toString(),
                'error_description' => $request->string('error_description')->toString(),
            ]);

            return redirect()->route('login')->with('error', __('auth.sso_failed'));
        }

        try {
            $microsoftUser = Socialite::driver('microsoft')->user();
        } catch (\Throwable $exception) {
            Log::error('Microsoft SSO callback failed', ['exception' => $exception]);

            return redirect()->route('login')->with('error', __('auth.sso_failed'));
        }

        $email = $microsoftUser->getEmail();

        if (! $email) {
            Log::warning('Microsoft SSO callback returned no email address', ['azure_id' => $microsoftUser->getId()]);

            return redirect()->route('login')->with('error', __('auth.sso_failed'));
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
