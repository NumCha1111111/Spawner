CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'USER' CHECK (role IN ('USER', 'ADMIN')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS cages (
    id BIGSERIAL PRIMARY KEY,
    cage_type TEXT NOT NULL UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cage_balances (
    user_id BIGINT NOT NULL REFERENCES users(id),
    cage_id BIGINT NOT NULL REFERENCES cages(id),
    quantity INTEGER NOT NULL DEFAULT 0 CHECK (quantity >= 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, cage_id)
);

CREATE TABLE IF NOT EXISTS trust_scores (
    user_id BIGINT PRIMARY KEY REFERENCES users(id),
    score DOUBLE PRECISION NOT NULL DEFAULT 80 CHECK (score >= 0 AND score <= 100),
    successful_count INTEGER NOT NULL DEFAULT 0,
    flagged_count INTEGER NOT NULL DEFAULT 0,
    rejected_count INTEGER NOT NULL DEFAULT 0,
    reversed_count INTEGER NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cage_transactions (
    id BIGSERIAL PRIMARY KEY,
    transaction_id TEXT NOT NULL UNIQUE,
    batch_id TEXT,
    user_id BIGINT NOT NULL REFERENCES users(id),
    cage_id BIGINT NOT NULL REFERENCES cages(id),
    quantity INTEGER NOT NULL,
    previous_quantity INTEGER NOT NULL,
    new_quantity INTEGER NOT NULL,
    action TEXT NOT NULL CHECK (action IN ('ADD', 'REMOVE', 'REVERSE', 'ADJUSTMENT')),
    risk_score INTEGER NOT NULL DEFAULT 0,
    risk_level TEXT NOT NULL DEFAULT 'NORMAL',
    trust_score DOUBLE PRECISION NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('APPROVED', 'FLAGGED', 'PENDING_REVIEW', 'BLOCKED', 'REJECTED', 'REVERSED')),
    reason TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMPTZ,
    reviewer_id BIGINT REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS risk_events (
    id BIGSERIAL PRIMARY KEY,
    transaction_id TEXT NOT NULL,
    user_id BIGINT NOT NULL REFERENCES users(id),
    rule_code TEXT NOT NULL,
    points INTEGER NOT NULL,
    details TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGSERIAL PRIMARY KEY,
    transaction_id TEXT NOT NULL,
    user_id BIGINT NOT NULL REFERENCES users(id),
    username TEXT NOT NULL,
    cage_type TEXT NOT NULL,
    quantity INTEGER NOT NULL,
    previous_quantity INTEGER NOT NULL,
    new_quantity INTEGER NOT NULL,
    action TEXT NOT NULL,
    risk_score INTEGER NOT NULL,
    risk_level TEXT NOT NULL,
    trust_score DOUBLE PRECISION NOT NULL,
    status TEXT NOT NULL,
    reason TEXT,
    created_at TIMESTAMPTZ NOT NULL,
    reviewed_at TIMESTAMPTZ,
    reviewer_id BIGINT REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS app_sessions (
    id TEXT PRIMARY KEY,
    data TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL
);

CREATE TABLE IF NOT EXISTS login_access_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    username TEXT NOT NULL,
    ip_address INET,
    country_code CHAR(2) CHECK (country_code IS NULL OR country_code ~ '^[A-Z]{2}$'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS request_idempotency (
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    operation TEXT NOT NULL,
    idempotency_key TEXT NOT NULL,
    request_hash CHAR(64) NOT NULL,
    response_payload JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, operation, idempotency_key)
);

CREATE TABLE IF NOT EXISTS system_failure_logs (
    id BIGSERIAL PRIMARY KEY,
    event_id TEXT NOT NULL UNIQUE,
    route TEXT NOT NULL,
    error_type TEXT NOT NULL,
    error_message TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_transactions_user_created ON cage_transactions(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON cage_transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_batch ON cage_transactions(batch_id);
CREATE INDEX IF NOT EXISTS idx_risk_events_user_created ON risk_events(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_app_sessions_expires ON app_sessions(expires_at);
CREATE INDEX IF NOT EXISTS idx_login_access_logs_created ON login_access_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_login_access_logs_user_created ON login_access_logs(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_request_idempotency_created ON request_idempotency(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_system_failure_logs_created ON system_failure_logs(created_at DESC);

INSERT INTO cages (cage_type) VALUES
    ('Enderman'), ('Magma Cube'), ('Skeleton'), ('Zombie'), ('Creeper'),
    ('Zombie Villager'), ('Fox'), ('Breeze'), ('Zombie Piglin'), ('Blaze'),
    ('Witch'), ('Guardian'), ('Spider'), ('Copper'), ('Cow'), ('Slime'),
    ('Iron Golem'), ('Chicken'), ('Rabbit'), ('Piglin Brute'), ('Husk'),
    ('Pig'), ('Sheep'), ('Evoker')
ON CONFLICT (cage_type) DO NOTHING;
