<?php
namespace App\Http\Controllers\API;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends ApiController
{
    public function index()
    {
        $data = Setting::query()->pluck('value','key')->toArray();
        return $this->ok(['settings' => $data]);
    }

    public function update(Request $request)
    {
        $payload = $request->validate(['settings' => 'required|array']);
        foreach ($payload['settings'] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : (string)$value]);
        }
        return $this->ok(['updated' => true]);
    }
}

