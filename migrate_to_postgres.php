<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

if (databaseDriver() !== 'pgsql') {
    fwrite(STDERR, "Set DATABASE_URL to the destination PostgreSQL database before running this script.\n");
    exit(1);
}
if (!is_file(DB_FILE)) {
    fwrite(STDERR, "SQLite source database was not found at " . DB_FILE . "\n");
    exit(1);
}

$source = new PDO('sqlite:' . DB_FILE, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$target = db();

// Never merge into a destination that already contains application activity.
// A fresh database can contain users/cages/trust rows created by bootstrap.php.
foreach (['cage_balances', 'cage_transactions', 'risk_events', 'audit_logs'] as $table) {
    $count = (int) $target->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    if ($count !== 0) {
        fwrite(STDERR, "Migration stopped: destination table $table already contains data. Nothing was changed.\n");
        exit(1);
    }
}

foreach (['users' => 'username', 'cages' => 'cage_type'] as $table => $labelColumn) {
    $sourceLabels = $source->query("SELECT $labelColumn FROM $table")->fetchAll(PDO::FETCH_COLUMN);
    $sourceLabels = array_fill_keys(array_map('strval', $sourceLabels), true);
    $destinationLabels = $target->query("SELECT $labelColumn FROM $table")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($destinationLabels as $label) {
        if (!isset($sourceLabels[(string) $label])) {
            fwrite(STDERR, "Migration stopped: destination $table contains an unexpected seeded row. Nothing was changed.\n");
            exit(1);
        }
    }
}

/** @return array<int, int> */
function buildIdMap(PDO $source, PDO $target, string $table, string $labelColumn): array
{
    $targetRows = $target->query("SELECT id, $labelColumn FROM $table")->fetchAll();
    $targetByLabel = [];
    foreach ($targetRows as $row) $targetByLabel[(string) $row[$labelColumn]] = (int) $row['id'];

    $map = [];
    $sourceRows = $source->query("SELECT id, $labelColumn FROM $table")->fetchAll();
    foreach ($sourceRows as $row) {
        $label = (string) $row[$labelColumn];
        if (!isset($targetByLabel[$label])) throw new RuntimeException("Missing destination $table row: $label");
        $map[(int) $row['id']] = $targetByLabel[$label];
    }
    return $map;
}

/** @param array<int, int> $map */
function mappedId(array $map, mixed $value, string $column): ?int
{
    if ($value === null || $value === '') return null;
    $sourceId = (int) $value;
    if (!isset($map[$sourceId])) throw new RuntimeException("Missing ID mapping for $column=$sourceId");
    return $map[$sourceId];
}

$target->beginTransaction();
try {
    $insertUser = $target->prepare('INSERT INTO users (username, password_hash, role, created_at, last_login_at)
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT (username) DO UPDATE SET
            password_hash = EXCLUDED.password_hash,
            role = EXCLUDED.role,
            created_at = EXCLUDED.created_at,
            last_login_at = EXCLUDED.last_login_at');
    foreach ($source->query('SELECT username, password_hash, role, created_at, last_login_at FROM users')->fetchAll() as $row) {
        $insertUser->execute(array_values($row));
    }

    $insertCage = $target->prepare('INSERT INTO cages (cage_type, created_at) VALUES (?, ?)
        ON CONFLICT (cage_type) DO UPDATE SET created_at = EXCLUDED.created_at');
    foreach ($source->query('SELECT cage_type, created_at FROM cages')->fetchAll() as $row) {
        $insertCage->execute(array_values($row));
    }

    $userIds = buildIdMap($source, $target, 'users', 'username');
    $cageIds = buildIdMap($source, $target, 'cages', 'cage_type');

    $insertTrust = $target->prepare('INSERT INTO trust_scores
        (user_id, score, successful_count, flagged_count, rejected_count, reversed_count, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT (user_id) DO UPDATE SET
            score = EXCLUDED.score,
            successful_count = EXCLUDED.successful_count,
            flagged_count = EXCLUDED.flagged_count,
            rejected_count = EXCLUDED.rejected_count,
            reversed_count = EXCLUDED.reversed_count,
            updated_at = EXCLUDED.updated_at');
    foreach ($source->query('SELECT * FROM trust_scores')->fetchAll() as $row) {
        $row['user_id'] = mappedId($userIds, $row['user_id'], 'trust_scores.user_id');
        $insertTrust->execute(array_values($row));
    }

    $insertBalance = $target->prepare('INSERT INTO cage_balances (user_id, cage_id, quantity, updated_at) VALUES (?, ?, ?, ?)');
    foreach ($source->query('SELECT * FROM cage_balances')->fetchAll() as $row) {
        $row['user_id'] = mappedId($userIds, $row['user_id'], 'cage_balances.user_id');
        $row['cage_id'] = mappedId($cageIds, $row['cage_id'], 'cage_balances.cage_id');
        $insertBalance->execute(array_values($row));
    }

    $transactionColumns = 'id, transaction_id, batch_id, user_id, cage_id, quantity, previous_quantity, new_quantity, action, risk_score, risk_level, trust_score, status, reason, created_at, reviewed_at, reviewer_id';
    $insertTransaction = $target->prepare('INSERT INTO cage_transactions (' . $transactionColumns . ')
        VALUES (' . implode(', ', array_fill(0, 17, '?')) . ')');
    foreach ($source->query('SELECT ' . $transactionColumns . ' FROM cage_transactions')->fetchAll() as $row) {
        $row['user_id'] = mappedId($userIds, $row['user_id'], 'cage_transactions.user_id');
        $row['cage_id'] = mappedId($cageIds, $row['cage_id'], 'cage_transactions.cage_id');
        $row['reviewer_id'] = mappedId($userIds, $row['reviewer_id'], 'cage_transactions.reviewer_id');
        $insertTransaction->execute(array_values($row));
    }

    $insertRisk = $target->prepare('INSERT INTO risk_events
        (id, transaction_id, user_id, rule_code, points, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($source->query('SELECT * FROM risk_events')->fetchAll() as $row) {
        $row['user_id'] = mappedId($userIds, $row['user_id'], 'risk_events.user_id');
        $insertRisk->execute(array_values($row));
    }

    $auditColumns = 'id, transaction_id, user_id, username, cage_type, quantity, previous_quantity, new_quantity, action, risk_score, risk_level, trust_score, status, reason, created_at, reviewed_at, reviewer_id';
    $insertAudit = $target->prepare('INSERT INTO audit_logs (' . $auditColumns . ')
        VALUES (' . implode(', ', array_fill(0, 17, '?')) . ')');
    foreach ($source->query('SELECT ' . $auditColumns . ' FROM audit_logs')->fetchAll() as $row) {
        $row['user_id'] = mappedId($userIds, $row['user_id'], 'audit_logs.user_id');
        $row['reviewer_id'] = mappedId($userIds, $row['reviewer_id'], 'audit_logs.reviewer_id');
        $insertAudit->execute(array_values($row));
    }

    foreach (['users', 'cages', 'cage_transactions', 'risk_events', 'audit_logs'] as $table) {
        $target->exec("SELECT setval(pg_get_serial_sequence('$table', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM $table");
    }
    $target->commit();
} catch (Throwable $error) {
    if ($target->inTransaction()) $target->rollBack();
    fwrite(STDERR, "Migration failed: " . $error->getMessage() . "\n");
    exit(1);
}

$transactionCount = (int) $target->query('SELECT COUNT(*) FROM cage_transactions')->fetchColumn();
$balanceTotal = (int) $target->query('SELECT COALESCE(SUM(quantity), 0) FROM cage_balances')->fetchColumn();
echo "Migration complete. Transactions: $transactionCount, active cages: $balanceTotal\n";
