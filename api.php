<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/risk_engine.php';

$route = trim((string) ($_GET['route'] ?? ''), '/');
$parts = $route === '' ? [] : explode('/', $route);
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'POST' && $route !== 'auth/login') requireCsrf();

function resolveCage(PDO $pdo, string $input): array
{
    static $cageCache = [];
    $clean = static fn (string $value): string => preg_replace('/[^a-z0-9]/', '', strtolower(trim($value))) ?? '';
    $wanted = $clean($input);
    $cacheKey = spl_object_id($pdo);
    if (!isset($cageCache[$cacheKey])) {
        $cageCache[$cacheKey] = $pdo->query('SELECT id, cage_type FROM cages')->fetchAll();
    }
    $cages = $cageCache[$cacheKey];
    $matches = [];
    foreach ($cages as $cage) {
        $candidate = $clean($cage['cage_type']);
        if ($wanted === $candidate) return $cage;
        $matches[] = ['distance' => levenshtein($wanted, $candidate), 'cage' => $cage];
    }
    usort($matches, static fn (array $left, array $right): int => $left['distance'] <=> $right['distance']);
    $limit = max(1, min(3, (int) floor(strlen($wanted) / 5)));
    if (!$matches || $matches[0]['distance'] > $limit) {
        throw new RuntimeException('ไม่พบชนิดกรง: ' . $input);
    }
    if (isset($matches[1]) && $matches[1]['distance'] === $matches[0]['distance']) {
        throw new RuntimeException('ชื่อชนิดกรงกำกวม กรุณาพิมพ์ให้ชัดเจน: ' . $input);
    }
    return $matches[0]['cage'];
}

if ($route === 'auth/login' && $method === 'POST') {
    $data = input();
    $username = trim((string) ($data['username'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    if ($username === '' || mb_strlen($username) > 80) respond(['error' => 'กรุณาระบุชื่อผู้ใช้'], 422);
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) respond(['error' => 'ไม่พบผู้ใช้ โปรดติดต่อผู้ดูแลระบบ'], 401);
    if ($user['role'] === 'ADMIN' && !password_verify($password, $user['password_hash'])) {
        respond(['error' => 'รหัสผ่านผู้ดูแลระบบไม่ถูกต้อง'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    unset($_SESSION['csrf_token']);
    $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$user['id']]);
    respond(['user' => sessionUser(['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']]), 'csrf_token' => csrfToken()]);
}

if ($route === 'auth/logout' && $method === 'POST') {
    $_SESSION = [];
    session_destroy();
    respond(['ok' => true]);
}

if ($route === 'auth/me' && $method === 'GET') respond(['user' => sessionUser(currentUser()), 'csrf_token' => csrfToken()]);

if ($route === 'cages/types' && $method === 'GET') {
    requireUser();
    respond(['cages' => $pdo->query('SELECT id, cage_type FROM cages ORDER BY cage_type')->fetchAll()]);
}

if ($route === 'cages/balance' && $method === 'GET') {
    $user = requireUser();
    $stmt = $pdo->prepare("SELECT c.id, c.cage_type, COALESCE(b.quantity, 0) quantity
        FROM cages c LEFT JOIN cage_balances b ON b.cage_id = c.id AND b.user_id = ? ORDER BY c.cage_type");
    $stmt->execute([$user['id']]);
    $trust = trustScore($pdo, (int) $user['id']);
    $balances = $stmt->fetchAll();
    respond(['balances' => $balances, 'total_quantity' => array_sum(array_map(static fn (array $row): int => (int) $row['quantity'], $balances)), 'trust_score' => round($trust, 2)]);
}

if ($route === 'cages/global-balance' && $method === 'GET') {
    $viewer = requireUser();
    $stmt = $pdo->prepare("SELECT c.id, c.cage_type, COALESCE(SUM(b.quantity), 0) quantity
        FROM cages c
        LEFT JOIN cage_balances b ON b.cage_id = c.id
            AND (? = '1' OR NOT EXISTS (
                SELECT 1 FROM users hidden_user
                WHERE hidden_user.id = b.user_id AND hidden_user.username = ?
            ))
        GROUP BY c.id, c.cage_type ORDER BY quantity DESC, c.cage_type");
    $stmt->execute([isPrimaryAdmin($viewer) ? '1' : '0', primaryAdminUsername()]);
    $balances = $stmt->fetchAll();
    respond([
        'scope' => 'ALL_USERS',
        'balances' => $balances,
        'total_quantity' => array_sum(array_map(static fn (array $row): int => (int) $row['quantity'], $balances)),
    ]);
}

if ($route === 'cages/history' && $method === 'GET') {
    $user = requireUser();
    $stmt = $pdo->prepare("SELECT t.id, t.transaction_id, u.username, c.cage_type, t.quantity, t.previous_quantity, t.new_quantity,
        t.action, t.risk_score, t.risk_level, t.trust_score, t.status, t.reason, t.created_at, t.reviewed_at
        FROM cage_transactions t JOIN cages c ON c.id = t.cage_id JOIN users u ON u.id = t.user_id WHERE t.user_id = ? ORDER BY t.id DESC LIMIT 100");
    $stmt->execute([$user['id']]);
    respond(['history' => $stmt->fetchAll()]);
}

if (preg_match('#^cages/global-history(?:/(\d+))?$#', $route, $historyMatch) && $method === 'GET') {
    $viewer = requireUser();
    $page = max(1, (int) ($historyMatch[1] ?? 1));
    $perPage = 25;
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT t.id, t.transaction_id, u.username, c.cage_type, t.quantity, t.previous_quantity, t.new_quantity,
        t.action, t.risk_score, t.risk_level, t.trust_score, t.status, t.reason, t.created_at, t.reviewed_at
        FROM cage_transactions t JOIN cages c ON c.id = t.cage_id JOIN users u ON u.id = t.user_id
        WHERE (? = '1' OR (t.status = 'APPROVED' AND u.username <> ?))
        ORDER BY t.id DESC LIMIT ? OFFSET ?");
    $stmt->execute([isPrimaryAdmin($viewer) ? '1' : '0', primaryAdminUsername(), $perPage + 1, $offset]);
    $history = $stmt->fetchAll();
    $hasNext = count($history) > $perPage;
    if ($hasNext) array_pop($history);
    respond([
        'history' => $history,
        'page' => $page,
        'per_page' => $perPage,
        'has_previous' => $page > 1,
        'has_next' => $hasNext,
    ]);
}

if ($route === 'cages/my-total' && $method === 'GET') {
    $user = requireUser();
    $stmt = $pdo->prepare("SELECT c.cage_type,
        SUM(CASE WHEN t.action = 'ADD' THEN t.quantity ELSE -t.quantity END) total_quantity,
        SUM(CASE WHEN t.action = 'ADD' THEN t.quantity ELSE 0 END) added_quantity,
        SUM(CASE WHEN t.action = 'REMOVE' THEN t.quantity ELSE 0 END) removed_quantity,
        COUNT(t.id) transaction_count
        FROM cage_transactions t JOIN cages c ON c.id = t.cage_id
        WHERE t.user_id = ? AND t.status IN ('APPROVED', 'FLAGGED')
        GROUP BY t.cage_id, c.cage_type ORDER BY total_quantity DESC, c.cage_type");
    $stmt->execute([$user['id']]);
    $items = $stmt->fetchAll();
    respond([
        'scope' => 'CURRENT_USER',
        'username' => $user['username'],
        'items' => $items,
        'total_quantity' => array_sum(array_map(static fn (array $item): int => (int) $item['total_quantity'], $items)),
        'submitted_quantity' => array_sum(array_map(static fn (array $item): int => (int) $item['added_quantity'], $items)),
    ]);
}

if ($route === 'cages/transactions' && $method === 'POST') {
    $user = requireUser();
    $data = input();
    $items = $data['items'] ?? null;
    if (!is_array($items)) {
        $items = [['cage_type' => $data['cage_type'] ?? '', 'quantity' => $data['quantity'] ?? null]];
    }
    $action = strtoupper((string) ($data['action'] ?? 'ADD'));
    $reason = trim((string) ($data['reason'] ?? ''));
    if (count($items) < 1 || count($items) > 100 || !in_array($action, ['ADD', 'REMOVE'], true)) {
        respond(['error' => 'ข้อมูลรายการไม่ถูกต้อง'], 422);
    }
    $pdo->beginTransaction();
    try {
        $results = [];
        $batchId = bin2hex(random_bytes(12));
        foreach ($items as $item) {
            $cageType = trim((string) ($item['cage_type'] ?? ''));
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (!$cageType || $quantity === false || $quantity < 1 || $quantity > 10000) {
                throw new RuntimeException('ข้อมูลชนิดกรงหรือจำนวนไม่ถูกต้อง: ' . ($cageType ?: 'ไม่ระบุชนิดกรง'));
            }
            $resolvedCage = resolveCage($pdo, $cageType);
            $cageId = (int) $resolvedCage['id'];
            $cageType = $resolvedCage['cage_type'];
            $balanceStmt = $pdo->prepare('SELECT quantity FROM cage_balances WHERE user_id = ? AND cage_id = ?');
            $balanceStmt->execute([$user['id'], $cageId]);
            $previous = (int) ($balanceStmt->fetchColumn() ?: 0);
            if ($action === 'REMOVE' && $quantity > $previous) throw new RuntimeException('จำนวนกรงคงเหลือไม่เพียงพอ: ' . $cageType);
            $assessment = riskAssessment($pdo, (int) $user['id'], $cageId, (int) $quantity, $action);
            $requiresApproval = in_array($action, ['ADD', 'REMOVE'], true);
            if ($requiresApproval) {
                $assessment['status'] = 'PENDING_REVIEW';
                $assessment['level'] = 'REVIEW';
                $assessment['events'][] = [
                    'code' => $action === 'ADD' ? 'ADDITION_APPROVAL' : 'WITHDRAWAL_APPROVAL',
                    'points' => 0,
                    'details' => $action === 'ADD'
                        ? 'คำขอเพิ่มกรงต้องได้รับการอนุมัติจากผู้ดูแลระบบ'
                        : 'คำขอถอนกรงต้องได้รับการอนุมัติจากผู้ดูแลระบบ',
                ];
            }
            $transactionId = bin2hex(random_bytes(12));
            $newQuantity = $action === 'ADD' ? $previous + $quantity : $previous - $quantity;
            if (in_array($assessment['status'], ['APPROVED', 'FLAGGED'], true)) {
                $upsert = $pdo->prepare("INSERT INTO cage_balances (user_id, cage_id, quantity) VALUES (?, ?, ?)
                    ON CONFLICT(user_id, cage_id) DO UPDATE SET quantity = excluded.quantity, updated_at = CURRENT_TIMESTAMP");
                $upsert->execute([$user['id'], $cageId, $newQuantity]);
            } else $newQuantity = $previous;
            $trust = $requiresApproval
                ? trustScore($pdo, (int) $user['id'])
                : updateTrust($pdo, (int) $user['id'], $assessment['status']);
            $insert = $pdo->prepare("INSERT INTO cage_transactions
                (transaction_id, batch_id, user_id, cage_id, quantity, previous_quantity, new_quantity, action, risk_score, risk_level, trust_score, status, reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$transactionId, $batchId, $user['id'], $cageId, $quantity, $previous, $newQuantity, $action,
                $assessment['score'], $assessment['level'], $trust, $assessment['status'], $reason ?: null]);
            foreach ($assessment['events'] as $event) {
                $pdo->prepare('INSERT INTO risk_events (transaction_id, user_id, rule_code, points, details) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$transactionId, $user['id'], $event['code'], $event['points'], $event['details']]);
            }
            $pdo->prepare("INSERT INTO audit_logs
                (transaction_id, user_id, username, cage_type, quantity, previous_quantity, new_quantity, action, risk_score, risk_level, trust_score, status, reason, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)")
                ->execute([$transactionId, $user['id'], $user['username'], $cageType, $quantity, $previous, $newQuantity, $action,
                    $assessment['score'], $assessment['level'], $trust, $assessment['status'], $reason ?: null]);
            $results[] = ['cage_type' => $cageType, 'quantity' => $quantity, 'transaction_id' => $transactionId,
                'status' => $assessment['status'], 'risk_level' => $assessment['level'], 'risk_score' => $assessment['score']];
        }
        $pdo->commit();
        $pending = count(array_filter($results, static fn (array $result): bool => in_array($result['status'], ['PENDING_REVIEW', 'BLOCKED'], true)));
        respond(['message' => $pending ? 'บันทึกหลายรายการแล้ว มีรายการที่รอตรวจสอบหรือถูกระงับ' : 'บันทึกหลายรายการและอัปเดตยอดแล้ว', 'items' => $results]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error' => $e->getMessage()], 422);
    }
}

if ($route === 'admin/user-cages' && $method === 'POST') {
    $admin = requirePrimaryAdmin();
    $data = input();
    $username = trim((string) ($data['username'] ?? ''));
    $items = $data['items'] ?? null;
    $note = trim((string) ($data['reason'] ?? ''));
    if ($username === '' || mb_strlen($username) > 80 || preg_match('/[\x00-\x1F\x7F]/u', $username)) {
        respond(['error' => 'ชื่อผู้ใช้ต้องยาว 1-80 ตัวอักษรและไม่มีอักขระควบคุม'], 422);
    }
    if (mb_strtolower($username) === mb_strtolower(primaryAdminUsername())) {
        respond(['error' => 'ไม่สามารถเพิ่มกรงให้บัญชีผู้ดูแลหลักได้'], 422);
    }
    if (!is_array($items) || count($items) < 1 || count($items) > 100) {
        respond(['error' => 'กรุณาระบุรายการกรง 1-100 รายการ'], 422);
    }
    if (mb_strlen($note) > 250) respond(['error' => 'หมายเหตุต้องไม่เกิน 250 ตัวอักษร'], 422);

    try {
        $resolvedItems = [];
        foreach ($items as $item) {
            $cageType = trim((string) ($item['cage_type'] ?? ''));
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($cageType === '' || $quantity === false || $quantity < 1 || $quantity > 10000) {
                throw new RuntimeException('ข้อมูลชนิดกรงหรือจำนวนไม่ถูกต้อง: ' . ($cageType ?: 'ไม่ระบุชนิดกรง'));
            }
            $cage = resolveCage($pdo, $cageType);
            $cageId = (int) $cage['id'];
            if (!isset($resolvedItems[$cageId])) {
                $resolvedItems[$cageId] = ['id' => $cageId, 'cage_type' => $cage['cage_type'], 'quantity' => 0];
            }
            $resolvedItems[$cageId]['quantity'] += (int) $quantity;
            if ($resolvedItems[$cageId]['quantity'] > 10000) {
                throw new RuntimeException('จำนวนรวมต่อชนิดกรงต้องไม่เกิน 10,000: ' . $cage['cage_type']);
            }
        }

        $pdo->beginTransaction();
        $findUser = $pdo->prepare('SELECT id, username, role FROM users WHERE LOWER(username) = LOWER(?) ORDER BY id LIMIT 1');
        $findUser->execute([$username]);
        $target = $findUser->fetch();
        $created = false;
        if (!$target) {
            $insertUserSql = databaseDriver() === 'pgsql'
                ? "INSERT INTO users (username, password_hash, role) VALUES (?, '', 'USER') RETURNING id"
                : "INSERT INTO users (username, password_hash, role) VALUES (?, '', 'USER')";
            $insertUser = $pdo->prepare($insertUserSql);
            $insertUser->execute([$username]);
            $target = ['id' => databaseDriver() === 'pgsql' ? (int) $insertUser->fetchColumn() : (int) $pdo->lastInsertId(), 'username' => $username, 'role' => 'USER'];
            $created = true;
        }
        $trustInsertSql = databaseDriver() === 'pgsql'
            ? 'INSERT INTO trust_scores (user_id) VALUES (?) ON CONFLICT (user_id) DO NOTHING'
            : 'INSERT OR IGNORE INTO trust_scores (user_id) VALUES (?)';
        $pdo->prepare($trustInsertSql)->execute([$target['id']]);
        $trust = trustScore($pdo, (int) $target['id']);
        $batchId = bin2hex(random_bytes(12));
        $reason = $note !== '' ? $note : 'เพิ่มโดยผู้ดูแลระบบ';
        $balanceSql = 'SELECT quantity FROM cage_balances WHERE user_id = ? AND cage_id = ?' . (databaseDriver() === 'pgsql' ? ' FOR UPDATE' : '');
        $balanceStmt = $pdo->prepare($balanceSql);
        $upsertBalance = $pdo->prepare("INSERT INTO cage_balances (user_id, cage_id, quantity) VALUES (?, ?, ?)
            ON CONFLICT(user_id, cage_id) DO UPDATE SET quantity = excluded.quantity, updated_at = CURRENT_TIMESTAMP");
        $insertTransaction = $pdo->prepare("INSERT INTO cage_transactions
            (transaction_id, batch_id, user_id, cage_id, quantity, previous_quantity, new_quantity, action, risk_score, risk_level, trust_score, status, reason, reviewed_at, reviewer_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'ADD', ?, ?, ?, 'APPROVED', ?, CURRENT_TIMESTAMP, ?)");
        $insertAudit = $pdo->prepare("INSERT INTO audit_logs
            (transaction_id, user_id, username, cage_type, quantity, previous_quantity, new_quantity, action, risk_score, risk_level, trust_score, status, reason, created_at, reviewed_at, reviewer_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'ADD', ?, ?, ?, 'APPROVED', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?)");
        $insertRiskEvent = $pdo->prepare('INSERT INTO risk_events (transaction_id, user_id, rule_code, points, details) VALUES (?, ?, ?, ?, ?)');
        $results = [];
        foreach ($resolvedItems as $item) {
            $balanceStmt->execute([$target['id'], $item['id']]);
            $previous = (int) ($balanceStmt->fetchColumn() ?: 0);
            $newQuantity = $previous + $item['quantity'];
            $assessment = riskAssessment($pdo, (int) $target['id'], (int) $item['id'], (int) $item['quantity'], 'ADD');
            $upsertBalance->execute([$target['id'], $item['id'], $newQuantity]);
            $transactionId = bin2hex(random_bytes(12));
            $insertTransaction->execute([$transactionId, $batchId, $target['id'], $item['id'], $item['quantity'], $previous, $newQuantity,
                $assessment['score'], $assessment['level'], $trust, $reason, $admin['id']]);
            $insertAudit->execute([$transactionId, $target['id'], $target['username'], $item['cage_type'], $item['quantity'], $previous, $newQuantity,
                $assessment['score'], $assessment['level'], $trust, $reason, $admin['id']]);
            foreach ($assessment['events'] as $event) {
                $insertRiskEvent->execute([$transactionId, $target['id'], $event['code'], $event['points'], $event['details']]);
            }
            $results[] = ['cage_type' => $item['cage_type'], 'quantity' => $item['quantity'], 'new_quantity' => $newQuantity,
                'risk_score' => $assessment['score'], 'risk_level' => $assessment['level']];
        }
        $pdo->commit();
        respond([
            'message' => $created ? 'สร้างผู้ใช้และเพิ่มกรงเรียบร้อยแล้ว' : 'เพิ่มกรงให้ผู้ใช้เดิมเรียบร้อยแล้ว',
            'user' => ['id' => (int) $target['id'], 'username' => $target['username'], 'created' => $created],
            'batch_id' => $batchId,
            'items' => $results,
        ]);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error' => $error->getMessage()], 422);
    }
}

if ($route === 'admin/users' && $method === 'GET') {
    $admin = requireAdmin();
    if (!isPrimaryAdmin($admin)) {
        $stmt = $pdo->prepare("SELECT username
            FROM users WHERE username <> ? ORDER BY created_at DESC");
        $stmt->execute([primaryAdminUsername()]);
        $users = $stmt->fetchAll();
        respond(['users' => $users]);
    }
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.role, u.created_at, u.last_login_at,
        COALESCE(ts.score, 80) trust_score, COUNT(t.id) transactions
        FROM users u LEFT JOIN trust_scores ts ON ts.user_id = u.id
        LEFT JOIN cage_transactions t ON t.user_id = u.id
        WHERE u.username <> ?
        GROUP BY u.id, ts.score ORDER BY u.created_at DESC");
    $stmt->execute([primaryAdminUsername()]);
    $users = $stmt->fetchAll();
    respond(['users' => $users]);
}

if ($route === 'admin/user-inventories' && $method === 'GET') {
    requirePrimaryAdmin();
    $stmt = $pdo->prepare("SELECT u.id user_id, u.username, u.role, c.cage_type, b.quantity
        FROM users u
        LEFT JOIN cage_balances b ON b.user_id = u.id AND b.quantity > 0
        LEFT JOIN cages c ON c.id = b.cage_id
        WHERE (u.role = 'USER' OR b.user_id IS NOT NULL) AND u.username <> ?
        ORDER BY u.username, b.quantity DESC, c.cage_type");
    $stmt->execute([primaryAdminUsername()]);
    $rows = $stmt->fetchAll();
    $users = [];
    foreach ($rows as $row) {
        $userId = (int) $row['user_id'];
        if (!isset($users[$userId])) {
            $users[$userId] = [
                'id' => $userId,
                'username' => $row['username'],
                'total_quantity' => 0,
                'items' => [],
            ];
        }
        if ($row['cage_type'] !== null) {
            $quantity = (int) $row['quantity'];
            $users[$userId]['items'][] = [
                'cage_type' => $row['cage_type'],
                'quantity' => $quantity,
            ];
            $users[$userId]['total_quantity'] += $quantity;
        }
    }
    respond(['users' => array_values($users)]);
}

function submissionHistoryFilter(): array
{
    $where = ["t.action IN ('ADD', 'REMOVE')"];
    $params = [];
    $username = trim((string) ($_GET['username'] ?? ''));
    if ($username !== '') {
        $where[] = "LOWER(u.username) LIKE ? ESCAPE '\\'";
        $search = strtr(mb_strtolower(mb_substr($username, 0, 80)), ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
        $params[] = '%' . $search . '%';
    }
    $action = strtoupper((string) ($_GET['action'] ?? ''));
    if (in_array($action, ['ADD', 'REMOVE'], true)) {
        $where[] = 't.action = ?';
        $params[] = $action;
    }
    $allowedStatuses = ['APPROVED', 'FLAGGED', 'PENDING_REVIEW', 'BLOCKED', 'REJECTED'];
    $status = strtoupper((string) ($_GET['status'] ?? ''));
    if (in_array($status, $allowedStatuses, true)) {
        $where[] = 't.status = ?';
        $params[] = $status;
    }
    $dateFrom = (string) ($_GET['date_from'] ?? '');
    $from = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFrom);
    if ($from && $from->format('Y-m-d') === $dateFrom) {
        $where[] = 't.created_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    $dateTo = (string) ($_GET['date_to'] ?? '');
    $to = DateTimeImmutable::createFromFormat('!Y-m-d', $dateTo);
    if ($to && $to->format('Y-m-d') === $dateTo) {
        $where[] = 't.created_at < ?';
        $params[] = $to->modify('+1 day')->format('Y-m-d 00:00:00');
    }
    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

if ($route === 'admin/submission-history/export' && $method === 'GET') {
    requirePrimaryAdmin();
    $filter = submissionHistoryFilter();
    $groupExpression = 'COALESCE(t.batch_id, t.transaction_id)';
    $stmt = $pdo->prepare("SELECT $groupExpression group_id, t.created_at, u.username, t.action,
        c.cage_type, t.quantity, t.status
        FROM cage_transactions t
        JOIN users u ON u.id = t.user_id
        JOIN cages c ON c.id = t.cage_id
        WHERE {$filter['sql']}
        ORDER BY t.id DESC");
    $stmt->execute($filter['params']);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="submission-history-' . gmdate('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'wb');
    if ($output === false) throw new RuntimeException('Unable to create CSV output');
    fputcsv($output, ['รหัสรอบ', 'เวลา', 'ชื่อผู้ใช้', 'ประเภท', 'ชนิดกรง', 'จำนวน', 'สถานะ']);
    $csvSafe = static function (mixed $value): mixed {
        if (!is_string($value)) return $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    };
    while ($row = $stmt->fetch()) {
        $row['created_at'] = preg_replace('/\+00(?::?00)?$/', '', (string) $row['created_at']);
        fputcsv($output, array_map($csvSafe, $row));
    }
    fclose($output);
    exit;
}

if (preg_match('#^admin/submission-history(?:/(\d+))?$#', $route, $submissionHistoryMatch) && $method === 'GET') {
    requirePrimaryAdmin();
    $page = max(1, (int) ($submissionHistoryMatch[1] ?? 1));
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    $groupExpression = 'COALESCE(t.batch_id, t.transaction_id)';
    $filter = submissionHistoryFilter();
    $stmt = $pdo->prepare("SELECT $groupExpression group_id, MAX(t.id) last_id, MAX(t.created_at) created_at,
        u.username, t.action, SUM(t.quantity) total_quantity, COUNT(*) item_count
        FROM cage_transactions t
        JOIN users u ON u.id = t.user_id
        WHERE {$filter['sql']}
        GROUP BY $groupExpression, u.id, u.username, t.action
        ORDER BY MAX(t.id) DESC LIMIT ? OFFSET ?");
    $stmt->execute([...$filter['params'], $perPage + 1, $offset]);
    $groups = $stmt->fetchAll();
    $hasNext = count($groups) > $perPage;
    if ($hasNext) array_pop($groups);

    if ($groups) {
        $groupIds = array_column($groups, 'group_id');
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $detailStmt = $pdo->prepare("SELECT $groupExpression group_id, c.cage_type, t.quantity, t.status,
            t.reason, t.created_at, t.reviewed_at
            FROM cage_transactions t
            JOIN cages c ON c.id = t.cage_id
            JOIN users u ON u.id = t.user_id
            WHERE $groupExpression IN ($placeholders) AND {$filter['sql']}
            ORDER BY t.id");
        $detailStmt->execute([...$groupIds, ...$filter['params']]);
        $details = [];
        foreach ($detailStmt->fetchAll() as $row) {
            $details[$row['group_id']][] = [
                'cage_type' => $row['cage_type'],
                'quantity' => (int) $row['quantity'],
                'status' => $row['status'],
                'reason' => $row['reason'],
                'created_at' => $row['created_at'],
                'reviewed_at' => $row['reviewed_at'],
            ];
        }
        foreach ($groups as &$group) {
            $group['total_quantity'] = (int) $group['total_quantity'];
            $group['item_count'] = (int) $group['item_count'];
            $group['items'] = $details[$group['group_id']] ?? [];
            $statuses = array_values(array_unique(array_column($group['items'], 'status')));
            $group['status'] = count($statuses) === 1 ? $statuses[0] : 'MIXED';
            unset($group['last_id']);
        }
        unset($group);
    }

    respond([
        'batches' => $groups,
        'page' => $page,
        'per_page' => $perPage,
        'has_previous' => $page > 1,
        'has_next' => $hasNext,
    ]);
}

if ($route === 'admin/users' && $method === 'POST') {
    requireAdmin();
    $data = input();
    $username = trim((string) ($data['username'] ?? ''));
    if ($username === '' || mb_strlen($username) > 80) respond(['error' => 'ชื่อผู้ใช้ต้องยาว 1-80 ตัวอักษร'], 422);
    try {
        $pdo->beginTransaction();
        $insertUserSql = databaseDriver() === 'pgsql'
            ? "INSERT INTO users (username, password_hash, role) VALUES (?, '', 'USER') RETURNING id"
            : "INSERT INTO users (username, password_hash, role) VALUES (?, '', 'USER')";
        $stmt = $pdo->prepare($insertUserSql);
        $stmt->execute([$username]);
        $userId = databaseDriver() === 'pgsql' ? (int) $stmt->fetchColumn() : (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO trust_scores (user_id) VALUES (?)')->execute([$userId]);
        $pdo->commit();
        respond(['message' => 'เพิ่มผู้ใช้แล้ว', 'user' => ['id' => $userId, 'username' => $username, 'role' => 'USER']]);
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error' => in_array($error->getCode(), ['23000', '23505'], true) ? 'มีชื่อผู้ใช้นี้อยู่แล้ว' : 'ไม่สามารถเพิ่มผู้ใช้ได้'], 422);
    }
}

if ($route === 'admin/overview' && $method === 'GET') {
    $admin = requireAdmin();
    if (!canViewRiskDashboard($admin)) {
        respond(['summary' => [], 'suspicious_users' => [], 'risk_trend' => []]);
    }
    $summary = $pdo->query("SELECT status, COUNT(*) count FROM cage_transactions GROUP BY status")->fetchAll();
    $users = $pdo->query("SELECT u.username, ts.score trust_score, COUNT(t.id) transactions
        FROM users u JOIN trust_scores ts ON ts.user_id = u.id LEFT JOIN cage_transactions t ON t.user_id = u.id
        WHERE u.role = 'USER' GROUP BY u.id, ts.score ORDER BY ts.score ASC LIMIT 10")->fetchAll();
    $recentRiskSql = databaseDriver() === 'pgsql'
        ? "SELECT DATE(created_at) AS event_day, COUNT(*) AS event_count FROM risk_events WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL '14 days' GROUP BY DATE(created_at) ORDER BY event_day"
        : "SELECT DATE(created_at) AS event_day, COUNT(*) AS event_count FROM risk_events WHERE created_at >= datetime('now', '-14 days') GROUP BY DATE(created_at) ORDER BY event_day";
    $events = $pdo->query($recentRiskSql)->fetchAll();
    respond(['summary' => $summary, 'suspicious_users' => $users, 'risk_trend' => $events]);
}

if ($route === 'admin/pending-reviews' && $method === 'GET') {
    $admin = requireAdmin();
    $riskAggregateSql = databaseDriver() === 'pgsql'
        ? "SELECT transaction_id, STRING_AGG(rule_code || ': ' || details, ' | ') risk_reasons FROM risk_events GROUP BY transaction_id"
        : "SELECT transaction_id, GROUP_CONCAT(rule_code || ': ' || details, ' | ') risk_reasons FROM risk_events GROUP BY transaction_id";
    $stmt = $pdo->prepare("SELECT t.id, t.transaction_id, t.batch_id, u.username, c.cage_type, t.quantity, t.action, t.risk_score,
        t.risk_level, t.trust_score, t.status, t.reason, t.created_at, r.risk_reasons
        FROM cage_transactions t JOIN users u ON u.id = t.user_id JOIN cages c ON c.id = t.cage_id
        LEFT JOIN ($riskAggregateSql) r ON r.transaction_id = t.transaction_id
        WHERE t.status IN ('PENDING_REVIEW', 'BLOCKED')
            AND (? = '1' OR u.username <> ?)
        ORDER BY t.risk_score DESC, t.id DESC LIMIT 200");
    $stmt->execute([isPrimaryAdmin($admin) ? '1' : '0', primaryAdminUsername()]);
    $groups = [];
    foreach ($stmt->fetchAll() as $row) {
        $groupId = $row['batch_id'] ?: $row['transaction_id'];
        if (!isset($groups[$groupId])) {
            $groups[$groupId] = [
                'group_id' => $groupId,
                'username' => $row['username'],
                'action' => $row['action'],
                'trust_score' => (float) $row['trust_score'],
                'risk_score' => (int) $row['risk_score'],
                'risk_level' => $row['risk_level'],
                'status' => $row['status'],
                'reason' => $row['reason'],
                'created_at' => $row['created_at'],
                'total_quantity' => 0,
                'items' => [],
                'risk_reasons' => [],
            ];
        }
        $groups[$groupId]['total_quantity'] += (int) $row['quantity'];
        $groups[$groupId]['risk_score'] = max($groups[$groupId]['risk_score'], (int) $row['risk_score']);
        $groups[$groupId]['items'][] = [
            'id' => (int) $row['id'],
            'cage_type' => $row['cage_type'],
            'quantity' => (int) $row['quantity'],
            'action' => $row['action'],
            'status' => $row['status'],
        ];
        if ($row['risk_reasons']) $groups[$groupId]['risk_reasons'][] = $row['risk_reasons'];
    }
    foreach ($groups as &$group) {
        $group['item_count'] = count($group['items']);
        $group['risk_reasons'] = implode(' | ', array_unique($group['risk_reasons']));
        if (!isPrimaryAdmin($admin)) {
            unset($group['trust_score'], $group['risk_score'], $group['risk_level'], $group['risk_reasons']);
        }
    }
    unset($group);
    respond(['items' => array_values($groups)]);
}

if (preg_match('#^admin/review-batches/([a-f0-9]{24})/(approve|reject)$#', $route, $match) && $method === 'POST') {
    $admin = requireAdmin();
    $groupId = $match[1];
    $newStatus = $match[2] === 'approve' ? 'APPROVED' : 'REJECTED';
    $stmt = $pdo->prepare("SELECT t.* FROM cage_transactions t
        JOIN users u ON u.id = t.user_id
        WHERE COALESCE(t.batch_id, t.transaction_id) = ?
            AND t.status IN ('PENDING_REVIEW', 'BLOCKED')
            AND (? = '1' OR u.username <> ?)
        ORDER BY t.id");
    $stmt->execute([$groupId, isPrimaryAdmin($admin) ? '1' : '0', primaryAdminUsername()]);
    $transactions = $stmt->fetchAll();
    if (!$transactions) respond(['error' => 'ไม่พบชุดรายการที่รอการตรวจสอบ'], 404);
    $pdo->beginTransaction();
    try {
        foreach ($transactions as $tx) {
            if ($newStatus === 'APPROVED') {
                $balance = $pdo->prepare('SELECT quantity FROM cage_balances WHERE user_id = ? AND cage_id = ?');
                $balance->execute([$tx['user_id'], $tx['cage_id']]);
                $previous = (int) ($balance->fetchColumn() ?: 0);
                if ($tx['action'] === 'REMOVE' && (int) $tx['quantity'] > $previous) {
                    throw new RuntimeException('ยอดคงเหลือไม่เพียงพอสำหรับ ' . $tx['transaction_id']);
                }
                $newQuantity = $tx['action'] === 'ADD'
                    ? $previous + (int) $tx['quantity']
                    : $previous - (int) $tx['quantity'];
                $pdo->prepare("INSERT INTO cage_balances (user_id, cage_id, quantity) VALUES (?, ?, ?)
                    ON CONFLICT(user_id, cage_id) DO UPDATE SET quantity = excluded.quantity, updated_at = CURRENT_TIMESTAMP")
                    ->execute([$tx['user_id'], $tx['cage_id'], $newQuantity]);
                $pdo->prepare('UPDATE cage_transactions SET previous_quantity = ?, new_quantity = ? WHERE id = ?')
                    ->execute([$previous, $newQuantity, $tx['id']]);
                $pdo->prepare('UPDATE audit_logs SET previous_quantity = ?, new_quantity = ? WHERE transaction_id = ?')
                    ->execute([$previous, $newQuantity, $tx['transaction_id']]);
            }
        }
        $trust = updateTrust($pdo, (int) $transactions[0]['user_id'], $newStatus);
        foreach ($transactions as $tx) {
            $pdo->prepare('UPDATE cage_transactions SET status = ?, trust_score = ?, reviewed_at = CURRENT_TIMESTAMP, reviewer_id = ? WHERE id = ?')
                ->execute([$newStatus, $trust, $admin['id'], $tx['id']]);
            $pdo->prepare('UPDATE audit_logs SET status = ?, trust_score = ?, reviewed_at = CURRENT_TIMESTAMP, reviewer_id = ? WHERE transaction_id = ?')
                ->execute([$newStatus, $trust, $admin['id'], $tx['transaction_id']]);
        }
        $pdo->commit();
        respond(['message' => 'บันทึกผลการตรวจสอบทั้งชุดแล้ว']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error' => $e->getMessage()], 422);
    }
}

if (count($parts) === 4 && $parts[0] === 'admin' && $parts[1] === 'reviews' && $parts[3] === '' && in_array($parts[2], ['approve', 'reject'], true)) {
    respond(['error' => 'Invalid review route'], 404);
}

if (preg_match('#^admin/reviews/(\d+)/(approve|reject)$#', $route, $match) && $method === 'POST') {
    $admin = requireAdmin();
    $id = (int) $match[1];
    $newStatus = $match[2] === 'approve' ? 'APPROVED' : 'REJECTED';
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT t.* FROM cage_transactions t
            JOIN users u ON u.id = t.user_id
            WHERE t.id = ? AND (? = '1' OR u.username <> ?)");
        $stmt->execute([$id, isPrimaryAdmin($admin) ? '1' : '0', primaryAdminUsername()]);
        $tx = $stmt->fetch();
        if (!$tx || !in_array($tx['status'], ['PENDING_REVIEW', 'BLOCKED'], true)) throw new RuntimeException('รายการนี้ตรวจไปแล้วหรือไม่พบข้อมูล');
        if ($newStatus === 'APPROVED') {
            $balance = $pdo->prepare('SELECT quantity FROM cage_balances WHERE user_id = ? AND cage_id = ?');
            $balance->execute([$tx['user_id'], $tx['cage_id']]);
            $previous = (int) ($balance->fetchColumn() ?: 0);
            if ($tx['action'] === 'REMOVE' && $tx['quantity'] > $previous) {
                throw new RuntimeException('ไม่สามารถอนุมัติได้: ยอดคงเหลือปัจจุบันไม่เพียงพอ');
            }
            $newQuantity = $tx['action'] === 'ADD' ? $previous + (int) $tx['quantity'] : $previous - (int) $tx['quantity'];
            $pdo->prepare("INSERT INTO cage_balances (user_id, cage_id, quantity) VALUES (?, ?, ?)
                ON CONFLICT(user_id, cage_id) DO UPDATE SET quantity = excluded.quantity, updated_at = CURRENT_TIMESTAMP")
                ->execute([$tx['user_id'], $tx['cage_id'], $newQuantity]);
            $pdo->prepare('UPDATE cage_transactions SET previous_quantity = ?, new_quantity = ? WHERE id = ?')
                ->execute([$previous, $newQuantity, $id]);
            $pdo->prepare('UPDATE audit_logs SET previous_quantity = ?, new_quantity = ? WHERE transaction_id = ?')
                ->execute([$previous, $newQuantity, $tx['transaction_id']]);
        }
        $trust = updateTrust($pdo, (int) $tx['user_id'], $newStatus);
        $pdo->prepare('UPDATE cage_transactions SET status = ?, trust_score = ?, reviewed_at = CURRENT_TIMESTAMP, reviewer_id = ? WHERE id = ?')
            ->execute([$newStatus, $trust, $admin['id'], $id]);
        $pdo->prepare('UPDATE audit_logs SET status = ?, trust_score = ?, reviewed_at = CURRENT_TIMESTAMP, reviewer_id = ? WHERE transaction_id = ?')
            ->execute([$newStatus, $trust, $admin['id'], $tx['transaction_id']]);
        $pdo->commit();
        respond(['message' => 'บันทึกผลการตรวจสอบแล้ว']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error' => $e->getMessage()], 422);
    }
}

if (preg_match('#^admin/transactions/(\d+)/reverse$#', $route, $match) && $method === 'POST') {
    $admin = requirePrimaryAdmin();
    $stmt = $pdo->prepare('SELECT * FROM cage_transactions WHERE id = ? AND status IN (\'APPROVED\', \'FLAGGED\')');
    $stmt->execute([(int) $match[1]]);
    $tx = $stmt->fetch();
    if (!$tx) respond(['error' => 'ไม่พบรายการที่สามารถย้อนกลับได้'], 404);
    $pdo->beginTransaction();
    try {
        $balance = $pdo->prepare('SELECT quantity FROM cage_balances WHERE user_id = ? AND cage_id = ?');
        $balance->execute([$tx['user_id'], $tx['cage_id']]);
        $previous = (int) ($balance->fetchColumn() ?: 0);
        $newQuantity = $tx['action'] === 'ADD' ? $previous - (int) $tx['quantity'] : $previous + (int) $tx['quantity'];
        if ($newQuantity < 0) throw new RuntimeException('ไม่สามารถย้อนรายการได้: ยอดคงเหลือปัจจุบันไม่เพียงพอ');
        $pdo->prepare("INSERT INTO cage_balances (user_id, cage_id, quantity) VALUES (?, ?, ?)
            ON CONFLICT(user_id, cage_id) DO UPDATE SET quantity = excluded.quantity, updated_at = CURRENT_TIMESTAMP")
            ->execute([$tx['user_id'], $tx['cage_id'], $newQuantity]);
        $trust = updateTrust($pdo, (int) $tx['user_id'], 'REVERSED');
        $pdo->prepare('UPDATE cage_transactions SET status = \'REVERSED\', reviewed_at = CURRENT_TIMESTAMP, reviewer_id = ?, trust_score = ? WHERE id = ?')
            ->execute([$admin['id'], $trust, $tx['id']]);
        $pdo->prepare('UPDATE audit_logs SET status = \'REVERSED\', reviewed_at = CURRENT_TIMESTAMP, reviewer_id = ?, trust_score = ? WHERE transaction_id = ?')
            ->execute([$admin['id'], $trust, $tx['transaction_id']]);
        $reverseId = bin2hex(random_bytes(12));
        $pdo->prepare('INSERT INTO cage_transactions (transaction_id, user_id, cage_id, quantity, previous_quantity, new_quantity, action, risk_score, risk_level, trust_score, status, reason, reviewed_at, reviewer_id) VALUES (?, ?, ?, ?, ?, ?, \'REVERSE\', 0, \'NORMAL\', ?, \'REVERSED\', ?, CURRENT_TIMESTAMP, ?)')
            ->execute([$reverseId, $tx['user_id'], $tx['cage_id'], $tx['quantity'], $previous, $newQuantity, $trust, 'ย้อนกลับรายการ #' . $tx['id'], $admin['id']]);
        $details = $pdo->prepare('SELECT u.username, c.cage_type FROM users u JOIN cages c ON c.id = ? WHERE u.id = ?');
        $details->execute([$tx['cage_id'], $tx['user_id']]);
        $reverseDetails = $details->fetch();
        $pdo->prepare("INSERT INTO audit_logs
            (transaction_id, user_id, username, cage_type, quantity, previous_quantity, new_quantity, action, risk_score, risk_level, trust_score, status, reason, created_at, reviewed_at, reviewer_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'REVERSE', 0, 'NORMAL', ?, 'REVERSED', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?)")
            ->execute([$reverseId, $tx['user_id'], $reverseDetails['username'], $reverseDetails['cage_type'], $tx['quantity'], $previous, $newQuantity, $trust, 'ย้อนกลับรายการ #' . $tx['id'], $admin['id']]);
        $pdo->commit();
        respond(['message' => 'ย้อนกลับรายการแล้ว']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error' => $e->getMessage()], 422);
    }
}

respond(['error' => 'ไม่พบ endpoint'], 404);
