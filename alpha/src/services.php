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

function alpha_members(PDO $db, array $ctx): array {
    if (!in_array($ctx['role'],['owner','admin'],true)) throw new RuntimeException('Owner or admin access required.');
    $q = $db->prepare('SELECT m.user_id,u.email,m.role,m.disabled_at FROM alpha_memberships m JOIN alpha_users u ON u.id=m.user_id WHERE m.business_id=? ORDER BY m.user_id');
    $q->execute([(int)$ctx['business_id']]);
    return $q->fetchAll();
}

/** Offboarding retains business-owned jobs and audit references. */
function alpha_disable_member(PDO $db, array $ctx, int $targetUserId): void {
    if (!in_array($ctx['role'],['owner','admin'],true) || $targetUserId < 1 || $targetUserId === (int)$ctx['user_id']) {
        throw new InvalidArgumentException('Cannot disable this member.');
    }
    $savepoint = alpha_tx_begin($db);
    try {
        $q = $db->prepare('SELECT role,disabled_at FROM alpha_memberships WHERE business_id=? AND user_id=? FOR UPDATE');
        $q->execute([(int)$ctx['business_id'],$targetUserId]);
        $member = $q->fetch();
        if (!$member || $member['disabled_at'] !== null || $member['role'] === 'owner'
            || ($member['role'] === 'admin' && $ctx['role'] !== 'owner')) {
            throw new InvalidArgumentException('Cannot disable this member.');
        }
        $q = $db->prepare('UPDATE alpha_memberships SET disabled_at=UTC_TIMESTAMP() WHERE business_id=? AND user_id=?');
        $q->execute([(int)$ctx['business_id'],$targetUserId]);
        alpha_tx_commit($db,$savepoint);
    } catch (Throwable $e) { alpha_tx_rollback($db,$savepoint); throw $e; }
}

function alpha_draft_times(string $start, string $end, string $timezone): array {
    if (!preg_match('/(Z|[+-][0-9]{2}:[0-9]{2})$/D',$start)
        || !preg_match('/(Z|[+-][0-9]{2}:[0-9]{2})$/D',$end)
        || !in_array($timezone,DateTimeZone::listIdentifiers(),true)) {
        throw new InvalidArgumentException('Start, end and time zone are required. Use an ISO date with an offset.');
    }
    try { $starts = new DateTimeImmutable($start); $ends = new DateTimeImmutable($end); }
    catch (Exception $e) { throw new InvalidArgumentException('Invalid dates.'); }
    if ($ends <= $starts || $ends->getTimestamp() - $starts->getTimestamp() > 7*86400
        || $starts->getTimestamp() < time() - 86400 || $starts->getTimestamp() > time() + 2*365*86400) {
        throw new InvalidArgumentException('Choose a future start and an end no more than seven days later.');
    }
    return [$starts->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        $ends->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
}

function alpha_create_calendar_draft(PDO $db, array $ctx, string $title, string $notes, string $start, string $end, string $timezone): int {
    $title = trim($title); $notes = trim($notes);
    if ($title === '' || mb_strlen($title) > 190 || mb_strlen($notes) > 10000) throw new InvalidArgumentException('Invalid draft details.');
    [$startUtc,$endUtc] = alpha_draft_times($start,$end,$timezone);
    $q = $db->prepare("INSERT INTO alpha_calendar_drafts (business_id,created_by,source,title,notes,starts_at_utc,ends_at_utc,timezone) VALUES (?,?,'manual',?,?,?,?,?)");
    $q->execute([(int)$ctx['business_id'],(int)$ctx['user_id'],$title,$notes,$startUtc,$endUtc,$timezone]);
    return (int)$db->lastInsertId();
}

function alpha_calendar_drafts(PDO $db, array $ctx): array {
    $q = $db->prepare('SELECT id,title,notes,starts_at_utc,ends_at_utc,timezone,source,state FROM alpha_calendar_drafts WHERE business_id=? ORDER BY starts_at_utc DESC LIMIT 100');
    $q->execute([(int)$ctx['business_id']]);
    return $q->fetchAll();
}

/** Trusted future calendar worker: only exact -EZ prefix yields a draft. */
function alpha_import_calendar_event(PDO $db, int $businessId, int $userId, string $provider, string $calendarId,
    string $eventId, string $summary, string $notes, string $start, string $end, string $timezone): ?int {
    if (!str_starts_with($summary,'-EZ ')) return null;
    $title = trim(substr($summary,4));
    if ($title === '' || mb_strlen($title) > 190 || mb_strlen($notes) > 10000
        || !in_array($provider,['google','icloud'],true) || $calendarId === '' || strlen($calendarId) > 190
        || $eventId === '' || strlen($eventId) > 190) throw new InvalidArgumentException('Invalid calendar event.');
    [$startUtc,$endUtc] = alpha_draft_times($start,$end,$timezone);
    $q = $db->prepare("INSERT INTO alpha_calendar_drafts (business_id,created_by,source,provider,calendar_id,event_id,title,notes,starts_at_utc,ends_at_utc,timezone) VALUES (?,?,'calendar',?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=IF(state='pending',VALUES(title),title),notes=IF(state='pending',VALUES(notes),notes),starts_at_utc=IF(state='pending',VALUES(starts_at_utc),starts_at_utc),ends_at_utc=IF(state='pending',VALUES(ends_at_utc),ends_at_utc),id=LAST_INSERT_ID(id)");
    $q->execute([$businessId,$userId,$provider,$calendarId,$eventId,$title,$notes,$startUtc,$endUtc,$timezone]);
    return (int)$db->lastInsertId();
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
    $q = $db->prepare("SELECT feature,COUNT(*) AS actions,SUM(units) AS units,unit_name,SUM(charge_cents) AS charge_cents FROM alpha_ai_usage WHERE business_id=? AND created_at>=? AND created_at<? AND state='completed' GROUP BY feature,unit_name ORDER BY feature,unit_name");
    $q->execute([$businessId,$start,$end]);
    $items = $q->fetchAll();
    $q = $db->prepare("SELECT COALESCE(SUM(charge_cents),0) FROM alpha_ai_usage WHERE business_id=? AND created_at>=? AND created_at<? AND state='reserved'");
    $q->execute([$businessId,$start,$end]);
    $reserved = (int)$q->fetchColumn();
    return ['currency'=>'AUD','period_start_utc'=>$start,'monthly_limit_cents'=>$limit === false ? 0 : (int)$limit,
        'spent_cents'=>array_sum(array_map(static fn(array $row): int => (int)$row['charge_cents'],$items)),
        'reserved_cents'=>$reserved,
        'features'=>$items];
}

function alpha_set_budget(PDO $db, array $ctx, int $limitCents): void {
    if ($ctx['role'] !== 'owner' || $limitCents < 0 || $limitCents > 1000000) throw new InvalidArgumentException('Owner approval and a valid monthly limit are required.');
    $q = $db->prepare('INSERT INTO alpha_ai_budgets (business_id,monthly_limit_cents) VALUES (?,?) ON DUPLICATE KEY UPDATE monthly_limit_cents=VALUES(monthly_limit_cents)');
    $q->execute([(int)$ctx['business_id'],$limitCents]);
}

/** Internal only. Reserve a worst-case amount BEFORE any external AI call. */
function alpha_reserve_usage(PDO $db, int $businessId, int $userId, string $feature, string $eventKey,
    float $units, string $unitName, int $maxChargeCents, int $maxProviderCostMicrousd): bool {
    if (!in_array($feature,['voice','slideshow_plan','narration','video_render','other'],true)
        || !preg_match('/^[a-zA-Z0-9:_-]{1,100}$/D',$eventKey)
        || $units <= 0 || $units > 1000000 || !is_finite($units)
        || !preg_match('/^[a-z_]{1,40}$/D',$unitName)
        || $maxChargeCents < 0 || $maxProviderCostMicrousd < 0) throw new InvalidArgumentException('Invalid usage event.');
    $savepoint = alpha_tx_begin($db);
    try {
        // The business budget row serialises all usage writes for this business.
        $q = $db->prepare('SELECT monthly_limit_cents,monthly_provider_limit_microusd FROM alpha_ai_budgets WHERE business_id=? FOR UPDATE');
        $q->execute([$businessId]);
        $budget = $q->fetch();
        if (!$budget || (int)$budget['monthly_limit_cents'] === 0 || (int)$budget['monthly_provider_limit_microusd'] === 0) throw new RuntimeException('AI is disabled for this business.');
        $q = $db->prepare('SELECT state FROM alpha_ai_usage WHERE business_id=? AND event_key=?');
        $q->execute([$businessId,$eventKey]);
        if ($q->fetch()) { alpha_tx_commit($db,$savepoint); return false; }
        $start = gmdate('Y-m-01 00:00:00');
        $end = gmdate('Y-m-01 00:00:00', strtotime('+1 month', strtotime($start)));
        $q = $db->prepare("SELECT COALESCE(SUM(CASE WHEN state<>'cancelled' THEN charge_cents ELSE 0 END),0) AS spent, COALESCE(SUM(provider_cost_microusd),0) AS provider_spent FROM alpha_ai_usage WHERE business_id=? AND created_at>=? AND created_at<?");
        $q->execute([$businessId,$start,$end]);
        $spent = $q->fetch();
        if ((int)$spent['spent'] + $maxChargeCents > (int)$budget['monthly_limit_cents']
            || (int)$spent['provider_spent'] + $maxProviderCostMicrousd > (int)$budget['monthly_provider_limit_microusd']) {
            throw new RuntimeException('Monthly AI limit reached.');
        }
        $q = $db->prepare("INSERT INTO alpha_ai_usage (business_id,user_id,feature,event_key,units,unit_name,charge_cents,provider_cost_microusd,state,expires_at) VALUES (?,?,?,?,?,?,?,?,'reserved',UTC_TIMESTAMP()+INTERVAL 2 HOUR)");
        $q->execute([$businessId,$userId,$feature,$eventKey,$units,$unitName,$maxChargeCents,$maxProviderCostMicrousd]);
        alpha_tx_commit($db,$savepoint);
        return true;
    } catch (Throwable $e) { alpha_tx_rollback($db,$savepoint); throw $e; }
}

/** Settle a reservation after successful work. Actual charges may only decrease. */
function alpha_complete_usage(PDO $db, int $businessId, string $eventKey, int $chargeCents, int $providerCostMicrousd): bool {
    if ($chargeCents < 0 || $providerCostMicrousd < 0) throw new InvalidArgumentException('Invalid charge.');
    $savepoint = alpha_tx_begin($db);
    try {
        $lock = $db->prepare('SELECT monthly_limit_cents FROM alpha_ai_budgets WHERE business_id=? FOR UPDATE');
        $lock->execute([$businessId]);
        if ($lock->fetchColumn() === false) throw new RuntimeException('AI is disabled for this business.');
        $q = $db->prepare('SELECT id,state,charge_cents,provider_cost_microusd,expires_at FROM alpha_ai_usage WHERE business_id=? AND event_key=? FOR UPDATE');
        $q->execute([$businessId,$eventKey]);
        $row = $q->fetch();
        if (!$row) throw new InvalidArgumentException('Reservation not found.');
        if ($row['state'] === 'completed') { alpha_tx_commit($db,$savepoint); return false; }
        if ($row['state'] !== 'reserved' || $row['expires_at'] <= gmdate('Y-m-d H:i:s')
            || $chargeCents > (int)$row['charge_cents'] || $providerCostMicrousd > (int)$row['provider_cost_microusd']) {
            throw new RuntimeException('Reservation expired or actual cost exceeded its maximum.');
        }
        $q = $db->prepare("UPDATE alpha_ai_usage SET state='completed',charge_cents=?,provider_cost_microusd=?,finished_at=UTC_TIMESTAMP() WHERE id=?");
        $q->execute([$chargeCents,$providerCostMicrousd,(int)$row['id']]);
        alpha_tx_commit($db,$savepoint);
        return true;
    } catch (Throwable $e) { alpha_tx_rollback($db,$savepoint); throw $e; }
}

/** Cancel with no customer charge; retain actual incurred provider cost for accounting. */
function alpha_cancel_usage(PDO $db, int $businessId, string $eventKey, int $providerCostMicrousd = 0): bool {
    if ($providerCostMicrousd < 0) throw new InvalidArgumentException('Invalid provider cost.');
    $savepoint = alpha_tx_begin($db);
    try {
        $lock = $db->prepare('SELECT business_id FROM alpha_ai_budgets WHERE business_id=? FOR UPDATE');
        $lock->execute([$businessId]);
        if (!$lock->fetch()) throw new InvalidArgumentException('Business not found.');
        $q = $db->prepare('SELECT id,state,provider_cost_microusd FROM alpha_ai_usage WHERE business_id=? AND event_key=? FOR UPDATE');
        $q->execute([$businessId,$eventKey]);
        $row = $q->fetch();
        if (!$row) throw new InvalidArgumentException('Reservation not found.');
        if ($row['state'] === 'cancelled') { alpha_tx_commit($db,$savepoint); return false; }
        if ($row['state'] !== 'reserved' || $providerCostMicrousd > (int)$row['provider_cost_microusd']) throw new RuntimeException('Invalid cancellation.');
        $q = $db->prepare("UPDATE alpha_ai_usage SET state='cancelled',charge_cents=0,provider_cost_microusd=?,finished_at=UTC_TIMESTAMP() WHERE id=?");
        $q->execute([$providerCostMicrousd,(int)$row['id']]);
        alpha_tx_commit($db,$savepoint);
        return true;
    } catch (Throwable $e) { alpha_tx_rollback($db,$savepoint); throw $e; }
}
