<?php
declare(strict_types=1);
// This is the only public PHP entry. Configuration and implementation stay
// outside public_html. Missing private installation fails closed.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
try {
    $private = dirname(__DIR__, 3) . '/smartdock-private-test';
    if (!is_file($private . '/config.php') || !is_file($private . '/app.php')) {
        http_response_code(503);
        echo '{"error":"Die Testanmeldung ist noch nicht eingerichtet."}';
        exit;
    }
    require $private . '/app.php';
    \Smartdock\run(require $private . '/config.php');
} catch (Throwable $error) {
    // Do not log payloads or PDO exception text containing connection details.
    error_log('SMARTDOCK test backend failed: ' . get_class($error));
    http_response_code(503);
    echo '{"error":"Der Dienst ist momentan nicht verfügbar."}';
}
