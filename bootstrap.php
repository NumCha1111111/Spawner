<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (getenv('VERCEL')) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

if (!getenv(DATABASE_URL_ENV) && !is_dir(DB_DIR)) {
    mkdir(DB_DIR, 0775, true);
}

function databaseDriver(): string
{
    return getenv(DATABASE_URL_ENV) ? 'pgsql' : 'sqlite';
}

function postgresDsn(string $databaseUrl): array
{
    $parts = parse_url($databaseUrl);
    if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
        throw new RuntimeException('DATABASE_URL is invalid');
    }
    parse_str($parts['query'] ?? '', $query);
    $sslMode = preg_replace('/[^a-z-]/i', '', (string) ($query['sslmode'] ?? 'require')) ?: 'require';
    $host = (string) $parts['host'];
    $endpointId = explode('.', $host, 2)[0];
    $options = '';
    if (preg_match('/^ep-[a-z0-9-]+$/i', $endpointId) === 1) {
        // Older libpq builds (including some XAMPP versions) cannot send SNI.
        // Neon accepts the compute endpoint as a startup option instead.
        $options = ';options=endpoint=' . $endpointId;
    }
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s%s',
        $host,
        (int) ($parts['port'] ?? 5432),
        ltrim($parts['path'], '/'),
        $sslMode,
        $options
    );
    return [$dsn, rawurldecode((string) ($parts['user'] ?? '')), rawurldecode((string) ($parts['pass'] ?? ''))];
}

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $initializeDatabase = databaseDriver() === 'pgsql'
            ? getenv('APP_BOOTSTRAP_DATABASE') === '1'
            : !is_file(DB_FILE);
        if (databaseDriver() === 'pgsql') {
            [$dsn, $username, $password] = postgresDsn((string) getenv(DATABASE_URL_ENV));
        } else {
            $dsn = 'sqlite:' . DB_FILE;
            $username = null;
            $password = null;
        }
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if (databaseDriver() === 'sqlite') $pdo->exec('PRAGMA foreign_keys = ON');
        if ($initializeDatabase) {
            $schemaFile = databaseDriver() === 'pgsql' ? 'schema.pgsql.sql' : 'schema.sql';
            $schema = file_get_contents(__DIR__ . '/' . $schemaFile);
            if ($schema === false) throw new RuntimeException('Database schema is unavailable');
            $pdo->exec($schema);
            ensureSchemaMigrations($pdo);
            seedUsers($pdo);
        }
    }
    return $pdo;
}

function seedInitialInventory(PDO $pdo): void
{
    $user = $pdo->query("SELECT id, username FROM users WHERE username = 'demo'")->fetch();
    if (!$user) return;
    $existing = $pdo->prepare('SELECT COUNT(*) FROM cage_transactions WHERE user_id = ?');
    $existing->execute([$user['id']]);
    if ((int) $existing->fetchColumn() > 0) return;

    $initial = [
        'Enderman' => 28, 'Magma Cube' => 18, 'Zombie Piglin' => 22, 'Cow' => 15,
        'Blaze' => 17, 'Guardian' => 15, 'Witch' => 16, 'Slime' => 15,
        'Iron Golem' => 16, 'Spider' => 10, 'Zombie' => 13, 'Creeper' => 13,
        'Breeze' => 14, 'Chicken' => 11, 'Rabbit' => 9, 'Piglin Brute' => 12,
        'Fox' => 12, 'Copper' => 11, 'Husk' => 6, 'Pig' => 7,
        'Sheep' => 10, 'Zombie Villager' => 8, 'Evoker' => 10, 'Skeleton' => 9,
    ];
    $trust = 80.0;
    $pdo->beginTransaction();
    try {
        foreach ($initial as $cageType => $quantity) {
            $cage = $pdo->prepare('SELECT id FROM cages WHERE cage_type = ?');
            $cage->execute([$cageType]);
            $cageId = (int) $cage->fetchColumn();
            $transactionId = 'initial-' . bin2hex(random_bytes(8));
            $pdo->prepare('INSERT INTO cage_balances (user_id, cage_id, quantity) VALUES (?, ?, ?)')
                ->execute([$user['id'], $cageId, $quantity]);
            $pdo->prepare("INSERT INTO cage_transactions
                (transaction_id, user_id, cage_id, quantity, previous_quantity, new_quantity, action, risk_score, risk_level, trust_score, status, reason)
                VALUES (?, ?, ?, ?, 0, ?, 'ADJUSTMENT', 0, 'NORMAL', ?, 'APPROVED', 'ยอดตั้งต้นจากรายการล่าสุด')")
                ->execute([$transactionId, $user['id'], $cageId, $quantity, $quantity, $trust]);
            $pdo->prepare("INSERT INTO audit_logs
                (transaction_id, user_id, username, cage_type, quantity, previous_quantity, new_quantity, action, risk_score, risk_level, trust_score, status, reason, created_at)
                VALUES (?, ?, ?, ?, ?, 0, ?, 'ADJUSTMENT', 0, 'NORMAL', ?, 'APPROVED', 'ยอดตั้งต้นจากรายการล่าสุด', CURRENT_TIMESTAMP)")
                ->execute([$transactionId, $user['id'], $user['username'], $cageType, $quantity, $quantity, $trust]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function seedUsers(PDO $pdo): void
{
    $admins = [
        [INITIAL_ADMIN_USERNAME_ENCODED, INITIAL_ADMIN_PASSWORD_HASH],
        [SECOND_ADMIN_USERNAME_ENCODED, SECOND_ADMIN_PASSWORD_HASH],
    ];
    $insertAdminSql = databaseDriver() === 'pgsql'
        ? "INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'ADMIN') ON CONFLICT (username) DO NOTHING"
        : "INSERT OR IGNORE INTO users (username, password_hash, role) VALUES (?, ?, 'ADMIN')";
    $insertTrustSql = databaseDriver() === 'pgsql'
        ? 'INSERT INTO trust_scores (user_id) VALUES (?) ON CONFLICT (user_id) DO NOTHING'
        : 'INSERT OR IGNORE INTO trust_scores (user_id) VALUES (?)';
    $insertAdmin = $pdo->prepare($insertAdminSql);
    $findAdmin = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'ADMIN'");
    $insertTrust = $pdo->prepare($insertTrustSql);

    foreach ($admins as [$encodedUsername, $passwordHash]) {
        $username = base64_decode($encodedUsername, true);
        if ($username === false || $username === '') {
            throw new RuntimeException('Invalid administrator configuration');
        }
        $insertAdmin->execute([$username, $passwordHash]);
        $findAdmin->execute([$username]);
        $adminId = $findAdmin->fetchColumn();
        if ($adminId !== false) $insertTrust->execute([(int) $adminId]);
    }
}

function ensureSchemaMigrations(PDO $pdo): void
{
    if (databaseDriver() === 'pgsql') return;
    $columns = $pdo->query('PRAGMA table_info(cage_transactions)')->fetchAll();
    $names = array_column($columns, 'name');
    if (!in_array('batch_id', $names, true)) {
        $pdo->exec('ALTER TABLE cage_transactions ADD COLUMN batch_id TEXT');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_transactions_batch ON cage_transactions(batch_id)');
    $pdo->exec("UPDATE cage_transactions AS current
        SET batch_id = (
            SELECT MIN(grouped.transaction_id)
            FROM cage_transactions AS grouped
            WHERE grouped.user_id = current.user_id
              AND grouped.action = current.action
              AND grouped.created_at = current.created_at
              AND COALESCE(grouped.reason, '') = COALESCE(current.reason, '')
              AND grouped.status IN ('PENDING_REVIEW', 'BLOCKED')
        )
        WHERE current.batch_id IS NULL
          AND current.status IN ('PENDING_REVIEW', 'BLOCKED')");
}

final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private PDO $pdo) {}

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare('SELECT data FROM app_sessions WHERE id = ? AND expires_at > CURRENT_TIMESTAMP');
        $stmt->execute([$id]);
        $data = $stmt->fetchColumn();
        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        $expiresAt = gmdate('Y-m-d H:i:sP', time() + (int) ini_get('session.gc_maxlifetime'));
        $stmt = $this->pdo->prepare("INSERT INTO app_sessions (id, data, expires_at) VALUES (?, ?, ?)
            ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, expires_at = EXCLUDED.expires_at");
        return $stmt->execute([$id, $data, $expiresAt]);
    }

    public function destroy(string $id): bool
    {
        return $this->pdo->prepare('DELETE FROM app_sessions WHERE id = ?')->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE expires_at <= CURRENT_TIMESTAMP');
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function validateId(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM app_sessions WHERE id = ? AND expires_at > CURRENT_TIMESTAMP');
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $lifetime = max(60, (int) ini_get('session.gc_maxlifetime'));
        $expiresAt = gmdate('Y-m-d H:i:sP', time() + $lifetime);
        $stmt = $this->pdo->prepare('UPDATE app_sessions SET expires_at = ? WHERE id = ?');
        $ok = $stmt->execute([$expiresAt, $id]);
        if ($ok && $stmt->rowCount() > 0) return true;
        return $this->write($id, $data);
    }
}

function startAppSession(bool $readOnly = false): void
{
    static $started = false;
    if ($started) return;

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    if (databaseDriver() === 'pgsql') {
        session_set_save_handler(new DatabaseSessionHandler(db()), true);
    }
    session_start($readOnly ? ['read_and_close' => true] : []);
    $started = true;
}

function csrfToken(): string
{
    startAppSession();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function requireCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        respond(['error' => 'Invalid CSRF token'], 403);
    }
}

function currentUser(): ?array
{
    startAppSession();
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id, username, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function primaryAdminUsername(): string
{
    $username = base64_decode(INITIAL_ADMIN_USERNAME_ENCODED, true);
    if (!is_string($username) || $username === '') throw new RuntimeException('Invalid primary administrator configuration');
    return $username;
}

function isPrimaryAdmin(array $user): bool
{
    return ($user['role'] ?? '') === 'ADMIN'
        && hash_equals(primaryAdminUsername(), (string) ($user['username'] ?? ''));
}

function usesUserView(array $user): bool
{
    return ($user['role'] ?? '') === 'USER'
        || (($user['role'] ?? '') === 'ADMIN' && !isPrimaryAdmin($user));
}

function canViewRiskDashboard(array $user): bool
{
    return isPrimaryAdmin($user);
}

function sessionUser(?array $user): ?array
{
    if ($user === null) return null;
    $user['can_view_risk_dashboard'] = canViewRiskDashboard($user);
    $user['is_primary_admin'] = isPrimaryAdmin($user);
    $user['uses_user_view'] = usesUserView($user);
    return $user;
}

function requireUser(): array
{
    $user = currentUser();
    if (!$user) respond(['error' => 'กรุณาเข้าสู่ระบบ'], 401);
    return $user;
}

function requireAdmin(): array
{
    $user = requireUser();
    if (($user['role'] ?? '') !== 'ADMIN') respond(['error' => 'ไม่มีสิทธิ์ดำเนินการ'], 403);
    return $user;
}

function requirePrimaryAdmin(): array
{
    $user = requireAdmin();
    if (!isPrimaryAdmin($user)) respond(['error' => 'สิทธิ์นี้สงวนไว้สำหรับผู้ดูแลระบบหลัก'], 403);
    return $user;
}

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : $_POST;
}

function jsonRequestMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) respond(['error' => 'Method not allowed'], 405);
}
