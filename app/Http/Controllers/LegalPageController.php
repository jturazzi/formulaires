<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LegalPageController extends Controller
{
    public function terms(): InertiaResponse
    {
        return $this->page('terms', 'messages.terms_title');
    }

    public function privacy(): InertiaResponse
    {
        return $this->page('privacy', 'messages.privacy_title');
    }

    private function page(string $key, string $titleKey): InertiaResponse
    {
        $locale = app()->getLocale();

        $content = Setting::get("{$key}_{$locale}") ?? Setting::get("{$key}_fr");

        return Inertia::render('public/Legal', [
            'title' => __($titleKey),
            'content' => $content,
        ]);
    }
}
