<?php
declare(strict_types=1);
require_once __DIR__.'/core.php';

function alpha_tx_begin(PDO $db): ?string {
    if (!$db->inTransaction()) { $db->beginTransaction(); return null; }
    $savepoint = 'alpha_sp_'.bin2hex(random_bytes(6));
    $db->exec('SAVEPOINT '.$savepoint);
    return $savepoint;
}
function alpha_tx_commit(PDO $db, ?string $savepoint): void {
    if ($savepoint === null) $db->commit();
    else $db->exec('RELEASE SAVEPOINT '.$savepoint);
}
function alpha_tx_rollback(PDO $db, ?string $savepoint): void {
    if ($savepoint === null) { if ($db->inTransaction()) $db->rollBack(); }
    else { $db->exec('ROLLBACK TO SAVEPOINT '.$savepoint); $db->exec('RELEASE SAVEPOINT '.$savepoint); }
}

/** Generates a single-use token for the operator to deliver to the named email address. */
function alpha_issue_token(PDO $db, int $businessId, string $email, string $purpose, string $role = 'staff'): string {
    $email = mb_strtolower(trim($email));
    if ($businessId < 1 || !filter_var($email,FILTER_VALIDATE_EMAIL)
        || !in_array($purpose,['invite','reset'],true) || !in_array($role,['owner','admin','staff'],true)) {
        throw new InvalidArgumentException('Invalid account token request.');
    }
    $savepoint = alpha_tx_begin($db);
    try {
        $q = $db->prepare('SELECT id FROM alpha_businesses WHERE id=? FOR UPDATE');
        $q->execute([$businessId]);
        if (!$q->fetch()) throw new InvalidArgumentException('Business not found.');
        $q = $db->prepare('SELECT u.id FROM alpha_users u JOIN alpha_memberships m ON m.user_id=u.id WHERE u.email=? AND m.business_id=?');
        $q->execute([$email,$businessId]);
        $existingMember = $q->fetch();
        if (($purpose === 'invite' && $existingMember) || ($purpose === 'reset' && !$existingMember)) {
            throw new InvalidArgumentException('Invitation or recovery is not available for this account.');
        }
        if ($purpose === 'invite') {
            $q = $db->prepare('SELECT id FROM alpha_users WHERE email=?');
            $q->execute([$email]);
            if ($q->fetch()) throw new InvalidArgumentException('Existing users need a separate membership invitation flow.');
        }
        $q = $db->prepare('UPDATE alpha_auth_tokens SET consumed_at=UTC_TIMESTAMP() WHERE business_id=? AND email=? AND purpose=? AND consumed_at IS NULL');
        $q->execute([$businessId,$email,$purpose]);
        $token = bin2hex(random_bytes(32));
        $q = $db->prepare('INSERT INTO alpha_auth_tokens (business_id,email,role,purpose,token_hash,expires_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP()+INTERVAL 24 HOUR)');
        $q->execute([$businessId,$email,$role,$purpose,hash('sha256',$token)]);
        alpha_tx_commit($db,$savepoint);
        return $token;
    } catch (Throwable $e) { alpha_tx_rollback($db,$savepoint); throw $e; }
}

function alpha_redeem_token(PDO $db, string $token, string $purpose, string $password): void {
    if (!preg_match('/^[a-f0-9]{64}$/D',$token) || !in_array($purpose,['invite','reset'],true)
        || strlen($password) < 12 || strlen($password) > 1024) throw new InvalidArgumentException('Invalid or expired link or password.');
    $savepoint = alpha_tx_begin($db);
    try {
        $q = $db->prepare('SELECT id,business_id,email,role FROM alpha_auth_tokens WHERE token_hash=? AND purpose=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE');
        $q->execute([hash('sha256',$token),$purpose]);
        $invite = $q->fetch();
        if (!$invite) throw new InvalidArgumentException('Invalid or expired link or password.');
        $hash = password_hash($password,PASSWORD_DEFAULT);
        if ($purpose === 'invite') {
            $q = $db->prepare('INSERT INTO alpha_users (email,password_hash,email_verified_at) VALUES (?,?,UTC_TIMESTAMP())');
            $q->execute([$invite['email'],$hash]);
            $userId = (int)$db->lastInsertId();
            $q = $db->prepare('INSERT INTO alpha_memberships (business_id,user_id,role) VALUES (?,?,?)');
            $q->execute([(int)$invite['business_id'],$userId,$invite['role']]);
        } else {
            $q = $db->prepare('UPDATE alpha_users u JOIN alpha_memberships m ON m.user_id=u.id SET u.password_hash=? WHERE u.email=? AND m.business_id=? AND u.disabled_at IS NULL AND m.disabled_at IS NULL');
            $q->execute([$hash,$invite['email'],(int)$invite['business_id']]);
            if ($q->rowCount() !== 1) throw new InvalidArgumentException('Account unavailable.');
        }
        $q = $db->prepare('UPDATE alpha_auth_tokens SET consumed_at=UTC_TIMESTAMP() WHERE id=?');
        $q->execute([(int)$invite['id']]);
        alpha_tx_commit($db,$savepoint);
    } catch (Throwable $e) { alpha_tx_rollback($db,$savepoint); throw $e; }
}

function alpha_submit_feedback(PDO $db, array $ctx, string $title, string $detail, string $area): int {
    $title = trim($title); $detail = trim($detail); $area = trim($area);
    if ($title === '' || mb_strlen($title) > 190 || $detail === '' || mb_strlen($detail) > 10000 || $area === '' || mb_strlen($area) > 80) {
        throw new InvalidArgumentException('Enter a title, area and description within the size limits.');
    }
    $q = $db->prepare('INSERT INTO alpha_feature_requests (business_id,user_id,title,detail,area) VALUES (?,?,?,?,?)');
    $q->execute([(int)$ctx['business_id'],(int)$ctx['user_id'],$title,$detail,$area]);
    return (int)$db->lastInsertId();
}

function alpha_feedback(PDO $db, array $ctx): array {
    // Staff see their own requests. Business owners/admins see only their own business.
    $admin = in_array($ctx['role'], ['owner','admin'], true);
    $sql = 'SELECT id,title,detail,area,status,priority,created_at,updated_at FROM alpha_feature_requests WHERE business_id=?';
    $params = [(int)$ctx['business_id']];
    if (!$admin) { $sql .= ' AND user_id=?'; $params[] = (int)$ctx['user_id']; }
    $q = $db->prepare($sql.' ORDER BY id DESC LIMIT 100');
    $q->execute($params);
    return $q->fetchAll();
}

function alpha_feedback_detail(PDO $db, array $ctx, int $requestId): ?array {
    $params = [(int)$ctx['business_id'],$requestId];
    $sql = 'SELECT id,title,detail,area,status,priority,created_at,updated_at FROM alpha_feature_requests WHERE business_id=? AND id=?';
    if ($ctx['role'] === 'staff') { $sql .= ' AND user_id=?'; $params[] = (int)$ctx['user_id']; }
    $q = $db->prepare($sql);
    $q->execute($params);
    $request = $q->fetch();
    if (!$request) return null;
    $updates = $db->prepare('SELECT message,author_label,created_at FROM alpha_feature_updates WHERE business_id=? AND request_id=? ORDER BY id ASC');
    $updates->execute([(int)$ctx['business_id'],$requestId]);
    $request['updates'] = $updates->fetchAll();
    return $request;
}

/** Trusted CLI moderation only; never expose this method on a public route. */
function alpha_triage_feedback(PDO $db, int $businessId, int $requestId, string $status, string $priority, string $message): void {
    if (!in_array($status, ['submitted','assessing','planned','in_progress','testing','released','declined'], true)
        || !in_array($priority, ['unset','low','medium','high'], true)
        || mb_strlen(trim($message)) > 10000) throw new InvalidArgumentException('Invalid triage update.');
    $savepoint = alpha_tx_begin($db);
    try {
        $q = $db->prepare('SELECT id FROM alpha_feature_requests WHERE business_id=? AND id=? FOR UPDATE');
        $q->execute([$businessId,$requestId]);
        if (!$q->fetch()) throw new InvalidArgumentException('Request not found.');
        $q = $db->prepare('UPDATE alpha_feature_requests SET status=?,priority=? WHERE business_id=? AND id=?');
        $q->execute([$status,$priority,$businessId,$requestId]);
        if (trim($message) !== '') {
            $q = $db->prepare('INSERT INTO alpha_feature_updates (business_id,request_id,message) VALUES (?,?,?)');
            $q->execute([$businessId,$requestId,trim($message)]);
        }
        alpha_tx_commit($db,$savepoint);
    } catch (Throwable $e) { alpha_tx_rollback($db,$savepoint); throw $e; }
}

function alpha_usage_summary(PDO $db, int $businessId): array {
    $start = gmdate('Y-m-01 00:00:00');
    $end = gmdate('Y-m-01 00:00:00', strtotime('+1 month', strtotime($start)));
    $q = $db->prepare('SELECT monthly_limit_cents FROM alpha_ai_budgets WHERE business_id=?');
    $q->execute([$businessId]);
    $limit = $q->fetchColumn();
    $q = $db->prepare('SELECT feature,COUNT(*) AS actions,SUM(units) AS units,unit_name,SUM(charge_cents) AS charge_cents FROM alpha_ai_usage WHERE business_id=? AND created_at>=? AND created_at<? GROUP BY feature,unit_name ORDER BY feature,unit_name');
    $q->execute([$businessId,$start,$end]);
    $items = $q->fetchAll();
    return ['currency'=>'AUD','period_start_utc'=>$start,'monthly_limit_cents'=>$limit === false ? 0 : (int)$limit,
        'spent_cents'=>array_sum(array_map(static fn(array $row): int => (int)$row['charge_cents'],$items)),
        'features'=>$items];
}

function alpha_set_budget(PDO $db, array $ctx, int $limitCents): void {
    if ($ctx['role'] !== 'owner' || $limitCents < 0 || $limitCents > 1000000) throw new InvalidArgumentException('Owner approval and a valid monthly limit are required.');
    $q = $db->prepare('INSERT INTO alpha_ai_budgets (business_id,monthly_limit_cents) VALUES (?,?) ON DUPLICATE KEY UPDATE monthly_limit_cents=VALUES(monthly_limit_cents)');
    $q->execute([(int)$ctx['business_id'],$limitCents]);
}

/** Internal server-side entry point, after provider work succeeds. Never accept cost or price from a client. */
function alpha_record_usage(PDO $db, int $businessId, int $userId, string $feature, string $eventKey,
    float $units, string $unitName, int $chargeCents, int $providerCostMicrousd): bool {
    if (!in_array($feature,['voice','slideshow_plan','narration','video_render','other'],true)
        || !preg_match('/^[a-zA-Z0-9:_-]{1,100}$/D',$eventKey)
        || $units <= 0 || $units > 1000000 || !is_finite($units)
        || !preg_match('/^[a-z_]{1,40}$/D',$unitName)
        || $chargeCents < 0 || $providerCostMicrousd < 0) throw new InvalidArgumentException('Invalid usage event.');
    $savepoint = alpha_tx_begin($db);
    try {
        // The business budget row serialises all usage writes for this business.
        $q = $db->prepare('SELECT monthly_limit_cents,monthly_provider_limit_microusd FROM alpha_ai_budgets WHERE business_id=? FOR UPDATE');
        $q->execute([$businessId]);
        $budget = $q->fetch();
        if (!$budget || (int)$budget['monthly_limit_cents'] === 0 || (int)$budget['monthly_provider_limit_microusd'] === 0) throw new RuntimeException('AI is disabled for this business.');
        $q = $db->prepare('SELECT id FROM alpha_ai_usage WHERE business_id=? AND event_key=?');
        $q->execute([$businessId,$eventKey]);
        if ($q->fetch()) { alpha_tx_commit($db,$savepoint); return false; }
        $start = gmdate('Y-m-01 00:00:00');
        $end = gmdate('Y-m-01 00:00:00', strtotime('+1 month', strtotime($start)));
        $q = $db->prepare('SELECT COALESCE(SUM(charge_cents),0) AS spent,COALESCE(SUM(provider_cost_microusd),0) AS provider_spent FROM alpha_ai_usage WHERE business_id=? AND created_at>=? AND created_at<?');
        $q->execute([$businessId,$start,$end]);
        $spent = $q->fetch();
        if ((int)$spent['spent'] + $chargeCents > (int)$budget['monthly_limit_cents']
            || (int)$spent['provider_spent'] + $providerCostMicrousd > (int)$budget['monthly_provider_limit_microusd']) {
            throw new RuntimeException('Monthly AI limit reached.');
        }
        $q = $db->prepare('INSERT INTO alpha_ai_usage (business_id,user_id,feature,event_key,units,unit_name,charge_cents,provider_cost_microusd) VALUES (?,?,?,?,?,?,?,?)');
        $q->execute([$businessId,$userId,$feature,$eventKey,$units,$unitName,$chargeCents,$providerCostMicrousd]);
        alpha_tx_commit($db,$savepoint);
        return true;
    } catch (Throwable $e) { alpha_tx_rollback($db,$savepoint); throw $e; }
}
