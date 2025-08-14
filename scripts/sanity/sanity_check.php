<?php
/**
 * CLI quick sanity over HTTP endpoints.
 * Usage:
 *   php scripts/sanity/sanity_check.php http://localhost/api
 */
$base = $argv[1] ?? 'http://localhost/api';
$trace = bin2hex(random_bytes(8));

function req($url, $trace) {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\nX-Trace-Id: {$trace}\r\n",
            'ignore_errors' => true,
            'timeout' => 5,
        ]
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    return [$code, $body];
}

$targets = ['/health', '/health/live', '/health/ready', '/health/openapi'];
$ok = true;
foreach ($targets as $t) {
    [$code, $body] = req(rtrim($base, '/') . $t, $trace);
    echo "GET {$t} => {$code}\n";
    if ($body !== false) echo substr($body, 0, 140) . "\n\n";
    if ($code >= 400) $ok = false;
}
exit($ok ? 0 : 2);
