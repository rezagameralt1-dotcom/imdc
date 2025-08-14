<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestPingMail;
use App\Jobs\TestPingJob;

class ToolsController extends Controller
{
    public function testMail(Request $request)
    {
        $to = $request->user()->email;
        Mail::to($to)->send(new TestPingMail("Hello from DigitalCity at ".now()));
        return back()->with('ok', "ایمیل تست به {$to} ارسال شد (mail driver: ".config('mail.default').").");
    }

    public function testJob()
    {
        TestPingJob::dispatch("Ping queued at ".now());
        return back()->with('ok', 'Job تست ارسال شد (queue='.config('queue.default').').');
    }
}

