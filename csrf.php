<?php
// CSRF protection helpers — require after session_start().

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';
    if ($expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><body><h2 style="color:red">Request blocked: invalid CSRF token.</h2>'
           . '<p><a href="javascript:history.back()">Go back</a></p></body></html>';
        exit;
    }
}
