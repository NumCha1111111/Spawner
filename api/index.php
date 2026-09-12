<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/api.php' || str_starts_with($path, '/api/')) {
    if (str_starts_with($path, '/api/')) {
        $_GET['route'] = rawurldecode(substr($path, 5));
    }
    try {
        require dirname(__DIR__) . '/api.php';
    } catch (Throwable $error) {
        $eventId = bin2hex(random_bytes(8));
        $route = trim((string) ($_GET['route'] ?? ''), '/');
        $safeMessage = function_exists('safeSystemErrorMessage') ? safeSystemErrorMessage($error) : 'Unhandled application error';
        error_log(sprintf('API failure [%s] %s: %s', $eventId, get_debug_type($error), $safeMessage));
        if (function_exists('recordSystemFailure')) recordSystemFailure($eventId, $route, $error);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว', 'event_id' => $eventId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

if ($path === '/' || $path === '/index.php') {
    require dirname(__DIR__) . '/index.php';
    exit;
}

if ($path === '/admin' || $path === '/admin.php') {
    require dirname(__DIR__) . '/admin.php';
    exit;
}

if ($path === '/user' || $path === '/user.php') {
    require dirname(__DIR__) . '/user.php';
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
