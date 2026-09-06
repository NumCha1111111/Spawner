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
$tables = [
    'users',
    'cages',
    'cage_balances',
    'trust_scores',
    'cage_transactions',
    'risk_events',
    'audit_logs',
];

$target->beginTransaction();
try {
    $target->exec('TRUNCATE TABLE audit_logs, risk_events, cage_transactions, cage_balances, trust_scores, cages, users RESTART IDENTITY CASCADE');
    foreach ($tables as $table) {
        $rows = $source->query('SELECT * FROM ' . $table)->fetchAll();
        if ($rows === []) continue;
        $columns = array_keys($rows[0]);
        $columnSql = implode(', ', array_map(static fn (string $column): string => '"' . $column . '"', $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $insert = $target->prepare("INSERT INTO $table ($columnSql) VALUES ($placeholders)");
        foreach ($rows as $row) $insert->execute(array_values($row));
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
