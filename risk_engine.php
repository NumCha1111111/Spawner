<?php
declare(strict_types=1);

function trustScore(PDO $pdo, int $userId): float
{
    $stmt = $pdo->prepare('SELECT score FROM trust_scores WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (float) ($stmt->fetchColumn() ?: 80);
}

function riskAssessment(PDO $pdo, int $userId, int $cageId, int $quantity, string $action): array
{
    $events = [];
    $score = 0;
    $now = time();
    $cageStats = $pdo->prepare("SELECT AVG(ABS(quantity)) average_qty, COUNT(*) total, MAX(ABS(quantity)) max_qty
        FROM cage_transactions WHERE user_id = ? AND cage_id = ? AND status IN ('APPROVED', 'FLAGGED')");
    $cageStats->execute([$userId, $cageId]);
    $stats = $cageStats->fetch() ?: ['average_qty' => 0, 'total' => 0, 'max_qty' => 0];
    $average = (float) ($stats['average_qty'] ?: 0);

    $addRule = static function (string $code, int $points, string $details) use (&$events, &$score): void {
        $events[] = compact('code', 'points', 'details');
        $score += $points;
    };

    if ($quantity >= 250) $addRule('SINGLE_EXTREME', 45, 'รายการเดียวมีจำนวนสูงผิดปกติ');
    elseif ($quantity >= 100) $addRule('SINGLE_LARGE', 25, 'รายการเดียวมีจำนวนสูงกว่าปกติ');
    elseif ($quantity >= 50) $addRule('SINGLE_ELEVATED', 12, 'จำนวนต่อรายการสูงกว่าช่วงปกติ');

    if ($average > 0 && $quantity > max(20, $average * 3)) {
        $addRule('PERSONAL_BASELINE', min(30, (int) round(($quantity / $average - 2) * 12)), 'แตกต่างจากค่าเฉลี่ยของผู้ใช้มาก');
    }

    $since1h = date('Y-m-d H:i:s', $now - 3600);
    $since15m = date('Y-m-d H:i:s', $now - 900);
    $since5m = date('Y-m-d H:i:s', $now - 300);
    $window = $pdo->prepare("SELECT COUNT(*) count, COALESCE(SUM(ABS(quantity)), 0) total
        FROM cage_transactions WHERE user_id = ? AND created_at >= ? AND status IN ('APPROVED', 'FLAGGED')");
    $window->execute([$userId, $since1h]);
    $hour = $window->fetch();
    if ((int) $hour['count'] >= 8 || (int) $hour['total'] + $quantity >= 300) $addRule('ROLLING_1H', 20, 'มียอดรวมในหนึ่งชั่วโมงสูงผิดปกติ');

    $window->execute([$userId, $since15m]);
    $quarter = $window->fetch();
    if ((int) $quarter['count'] >= 4 || (int) $quarter['total'] + $quantity >= 120) $addRule('ROLLING_15M', 18, 'มีรายการถี่หรือยอดรวมใน 15 นาทีสูง');

    $window->execute([$userId, $since5m]);
    $short = $window->fetch();
    if ((int) $short['count'] >= 3 && $quantity <= 20) $addRule('BURST_PATTERN', 20, 'ตรวจพบการเพิ่มจำนวนเล็กหลายครั้งติดกัน');

    $sameType = $pdo->prepare("SELECT COUNT(*) FROM cage_transactions
        WHERE user_id = ? AND cage_id = ? AND created_at >= ? AND status IN ('APPROVED', 'FLAGGED')");
    $sameType->execute([$userId, $cageId, $since15m]);
    if ((int) $sameType->fetchColumn() >= 3) $addRule('REPEATED_CAGE', 12, 'ชนิดกรงเดิมถูกเพิ่มซ้ำในช่วงเวลาสั้น');

    $daily = $pdo->prepare("SELECT COALESCE(SUM(ABS(quantity)), 0) FROM cage_transactions
        WHERE user_id = ? AND created_at >= ? AND status IN ('APPROVED', 'FLAGGED')");
    $daily->execute([$userId, date('Y-m-d 00:00:00')]);
    if ((int) $daily->fetchColumn() + $quantity >= 500) $addRule('DAILY_VOLUME', 15, 'ยอดรวมต่อวันสูงกว่าช่วงปกติ');

    $bad = $pdo->prepare("SELECT COUNT(*) FROM cage_transactions
        WHERE user_id = ? AND status IN ('REJECTED', 'BLOCKED', 'REVERSED') AND created_at >= ?");
    $bad->execute([$userId, date('Y-m-d H:i:s', $now - 30 * 86400)]);
    $badCount = (int) $bad->fetchColumn();
    if ($badCount >= 2) $addRule('NEGATIVE_HISTORY', min(15, $badCount * 4), 'มีประวัติรายการที่ต้องตรวจสอบ');

    $trust = trustScore($pdo, $userId);
    if ($trust < 50) $addRule('LOW_TRUST', 15, 'คะแนนความน่าเชื่อถืออยู่ในระดับต่ำ');
    elseif ($trust < 70) $addRule('WATCH_TRUST', 7, 'คะแนนความน่าเชื่อถืออยู่ในระดับเฝ้าระวัง');

    $peer = $pdo->prepare("SELECT AVG(ABS(quantity)) FROM cage_transactions
        WHERE cage_id = ? AND created_at >= ? AND status IN ('APPROVED', 'FLAGGED')");
    $peer->execute([$cageId, date('Y-m-d H:i:s', $now - 30 * 86400)]);
    $peerAverage = (float) ($peer->fetchColumn() ?: 0);
    if ($peerAverage > 0 && $quantity > max(50, $peerAverage * 5)) $addRule('PEER_DEVIATION', 8, 'แตกต่างจากรูปแบบรวมของระบบ');

    $score = min(100, max(0, $score));
    if ($score <= 20) [$level, $status] = ['NORMAL', 'APPROVED'];
    elseif ($score <= 50) [$level, $status] = ['WATCH', 'FLAGGED'];
    elseif ($score <= 80) [$level, $status] = ['SUSPICIOUS', 'PENDING_REVIEW'];
    else [$level, $status] = ['HIGH_RISK', 'BLOCKED'];

    return compact('score', 'level', 'status', 'trust', 'events');
}

function updateTrust(PDO $pdo, int $userId, string $status): float
{
    $delta = match ($status) {
        'APPROVED' => 0.25,
        'FLAGGED' => -0.5,
        'PENDING_REVIEW' => -1.0,
        'BLOCKED' => -2.0,
        'REJECTED' => -3.0,
        'REVERSED' => -1.0,
        default => 0.0,
    };
    $clamp = databaseDriver() === 'pgsql'
        ? 'LEAST(100, GREATEST(0, score + ?))'
        : 'MIN(100, MAX(0, score + ?))';
    $stmt = $pdo->prepare("UPDATE trust_scores SET score = $clamp,
        successful_count = successful_count + ?, flagged_count = flagged_count + ?,
        rejected_count = rejected_count + ?, reversed_count = reversed_count + ?, updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ?");
    $stmt->execute([$delta, $status === 'APPROVED' ? 1 : 0, $status === 'FLAGGED' ? 1 : 0,
        in_array($status, ['REJECTED', 'BLOCKED'], true) ? 1 : 0, $status === 'REVERSED' ? 1 : 0, $userId]);
    return trustScore($pdo, $userId);
}
