PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'USER' CHECK (role IN ('USER', 'ADMIN')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TEXT
);

CREATE TABLE IF NOT EXISTS cages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cage_type TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cage_balances (
    user_id INTEGER NOT NULL,
    cage_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 0 CHECK (quantity >= 0),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, cage_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (cage_id) REFERENCES cages(id)
);

CREATE TABLE IF NOT EXISTS trust_scores (
    user_id INTEGER PRIMARY KEY,
    score REAL NOT NULL DEFAULT 80 CHECK (score >= 0 AND score <= 100),
    successful_count INTEGER NOT NULL DEFAULT 0,
    flagged_count INTEGER NOT NULL DEFAULT 0,
    rejected_count INTEGER NOT NULL DEFAULT 0,
    reversed_count INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS cage_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id TEXT NOT NULL UNIQUE,
    batch_id TEXT,
    user_id INTEGER NOT NULL,
    cage_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    previous_quantity INTEGER NOT NULL,
    new_quantity INTEGER NOT NULL,
    action TEXT NOT NULL CHECK (action IN ('ADD', 'REMOVE', 'REVERSE', 'ADJUSTMENT')),
    risk_score INTEGER NOT NULL DEFAULT 0,
    risk_level TEXT NOT NULL DEFAULT 'NORMAL',
    trust_score REAL NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('APPROVED', 'FLAGGED', 'PENDING_REVIEW', 'BLOCKED', 'REJECTED', 'REVERSED')),
    reason TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TEXT,
    reviewer_id INTEGER,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (cage_id) REFERENCES cages(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS risk_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    rule_code TEXT NOT NULL,
    points INTEGER NOT NULL,
    details TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    username TEXT NOT NULL,
    cage_type TEXT NOT NULL,
    quantity INTEGER NOT NULL,
    previous_quantity INTEGER NOT NULL,
    new_quantity INTEGER NOT NULL,
    action TEXT NOT NULL,
    risk_score INTEGER NOT NULL,
    risk_level TEXT NOT NULL,
    trust_score REAL NOT NULL,
    status TEXT NOT NULL,
    reason TEXT,
    created_at TEXT NOT NULL,
    reviewed_at TEXT,
    reviewer_id INTEGER,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS login_access_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    username TEXT NOT NULL,
    ip_address TEXT,
    country_code TEXT CHECK (country_code IS NULL OR length(country_code) = 2),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_transactions_user_created ON cage_transactions(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON cage_transactions(status);
CREATE INDEX IF NOT EXISTS idx_risk_events_user_created ON risk_events(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_login_access_logs_created ON login_access_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_login_access_logs_user_created ON login_access_logs(user_id, created_at DESC);

INSERT OR IGNORE INTO cages (cage_type) VALUES
    ('Enderman'), ('Magma Cube'), ('Skeleton'), ('Zombie'), ('Creeper'),
    ('Zombie Villager'), ('Fox'), ('Breeze'), ('Zombie Piglin'), ('Blaze'),
    ('Witch'), ('Guardian'), ('Spider'), ('Copper'), ('Cow'), ('Slime'),
    ('Iron Golem'), ('Chicken'), ('Rabbit'), ('Piglin Brute'), ('Husk'),
    ('Pig'), ('Sheep'), ('Evoker');
