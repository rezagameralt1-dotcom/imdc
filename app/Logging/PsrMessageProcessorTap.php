<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class PsrMessageProcessorTap
{
    public function __invoke(Logger $logger): void
    {
        // Add PSR-3 {placeholder} interpolation safely at runtime (not in config)
        $logger->pushProcessor(new PsrLogMessageProcessor());
    }
}

