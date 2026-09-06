<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = currentUser();
if ($user !== null && ($user['role'] ?? '') !== 'ADMIN') {
    header('Location: user.php');
    exit;
}

define('PAGE_ROLE', 'ADMIN');
require __DIR__ . '/index.php';
