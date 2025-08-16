<?php

namespace App\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;

class CustomizeFormatter
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter($this->formatter());
        }
    }

    protected function formatter(): FormatterInterface
    {
        return new MaskingFormatter(null, null, true, true);
    }
}
