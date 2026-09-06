<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/api.php' || str_starts_with($path, '/api/')) {
    if (str_starts_with($path, '/api/')) {
        $_GET['route'] = rawurldecode(substr($path, 5));
    }
    require dirname(__DIR__) . '/api.php';
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
