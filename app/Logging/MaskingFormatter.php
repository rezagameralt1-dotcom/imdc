<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;

class MaskingFormatter extends LineFormatter
{
    protected array $patterns = [
        '/[\w._%+-]+@[\w.-]+\.[a-zA-Z]{2,}/' => '[masked-email]',
        '/\+?\d{9,15}/' => '[masked-phone]',
    ];

    public function format(array $record): string
    {
        $output = parent::format($record);
        foreach ($this->patterns as $pattern => $replacement) {
            $output = preg_replace($pattern, $replacement, $output);
        }
        return $output;
    }
}
