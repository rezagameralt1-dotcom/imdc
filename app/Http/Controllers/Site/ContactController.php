<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Setting;

class ContactController extends Controller
{
    public function form()
    {
        return view('site.contact.form');
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'name'    => ['required','string','max:150'],
            'email'   => ['required','email'],
            'message' => ['required','string','max:5000'],
        ]);

        $to = Setting::query()->where('key','contact_to')->value('value') ?: config('mail.from.address');

        Mail::raw(
            "Contact form:\n\nFrom: {$data['name']} <{$data['email']}>\n\nMessage:\n{$data['message']}",
            function($m) use ($to) {
                $m->to($to)->subject('DigitalCity Contact Form');
            }
        );

        return back()->with('success', 'Thanks! Your message was sent.');
    }
}

