<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    public function switch(string $locale)
    {
        $allowed = ['fa', 'en'];
        if (! in_array($locale, $allowed)) {
            $locale = config('app.fallback_locale');
        }
        Session::put('app_locale', $locale);
        return back()->with('status', __('messages.language_switched'));
    }
}

