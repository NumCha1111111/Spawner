<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = currentUser();
if ($user === null) {
    header('Location: index.php');
    exit;
}

if (!usesUserView($user)) {
    header('Location: admin.php');
    exit;
}

define('PAGE_ROLE', 'USER');
require __DIR__ . '/index.php';
