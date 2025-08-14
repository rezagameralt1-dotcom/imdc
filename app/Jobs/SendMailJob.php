<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $mailableClass;
    public array $mailableArgs;
    public string $to;

    public function __construct(string $mailableClass, array $mailableArgs, string $to)
    {
        $this->onQueue(config('queue.default', 'default'));
        $this->mailableClass = $mailableClass;
        $this->mailableArgs = $mailableArgs;
        $this->to = $to;
    }

    public function handle(): void
    {
        $class = $this->mailableClass;
        $mailable = new $class(...$this->mailableArgs);
        Mail::to($this->to)->send($mailable);
    }
}
