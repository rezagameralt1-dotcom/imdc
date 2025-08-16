<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $settings = Setting::query()->pluck('value', 'key')->toArray();

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'site_title' => ['nullable', 'string', 'max:150'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'twitter_handle' => ['nullable', 'string', 'max:50'],
            'meta_image_url' => ['nullable', 'url'],
            'maintenance' => ['nullable', 'boolean'],
            'contact_to' => ['nullable', 'email'],
        ]);

        $keys = ['site_title', 'site_tagline', 'twitter_handle', 'meta_image_url', 'contact_to'];
        foreach ($keys as $k) {
            if (array_key_exists($k, $data)) {
                Setting::updateOrCreate(['key' => $k], ['value' => $data[$k]]);
            }
        }

        // Maintenance toggle
        $maint = ! empty($data['maintenance']) ? '1' : '0';
        Setting::updateOrCreate(['key' => 'maintenance'], ['value' => $maint]);

        return back()->with('success', 'Settings updated.');
    }
}
