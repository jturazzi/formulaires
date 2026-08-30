<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SettingsController extends Controller
{
    public function edit(): InertiaResponse
    {
        return Inertia::render('admin/Settings', [
            'settings' => [
                'default_retention_days' => (int) Setting::get('default_retention_days', config('formulaires.default_retention_days')),
                'terms_fr' => Setting::get('terms_fr'),
                'terms_en' => Setting::get('terms_en'),
                'privacy_fr' => Setting::get('privacy_fr'),
                'privacy_en' => Setting::get('privacy_en'),
            ],
            'systemInfo' => $this->systemInfo(),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function systemInfo(): array
    {
        $connection = config('database.default');

        return [
            'app_version' => $this->appVersion(),
            'php_version' => PHP_VERSION,
            'laravel_version' => Application::VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
            'environment' => config('app.env'),
            'database' => sprintf('%s — %s', $connection, config("database.connections.{$connection}.database")),
            'cache' => config('cache.default'),
            'session' => config('session.driver'),
            'timezone' => config('app.timezone'),
        ];
    }

    private function appVersion(): ?string
    {
        $composer = json_decode(File::get(base_path('composer.json')), true);

        return $composer['version'] ?? null;
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'terms_fr' => ['nullable', 'string', 'max:100000'],
            'terms_en' => ['nullable', 'string', 'max:100000'],
            'privacy_fr' => ['nullable', 'string', 'max:100000'],
            'privacy_en' => ['nullable', 'string', 'max:100000'],
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value === null ? null : (string) $value);
        }

        return back()->with('success', __('messages.saved'));
    }
}
