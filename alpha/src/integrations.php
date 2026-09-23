<?php
declare(strict_types=1);
require_once __DIR__.'/core.php';

function alpha_integration_status(PDO $db, array $ctx): array {
    $businessId = (int)$ctx['business_id'];
    $q = $db->prepare('SELECT accounting_provider FROM alpha_businesses WHERE id=?');
    $q->execute([$businessId]);
    $provider = $q->fetchColumn();
    $q = $db->prepare('SELECT enabled,monthly_limit_cents,sender_label FROM alpha_sms_settings WHERE business_id=?');
    $q->execute([$businessId]);
    $sms = $q->fetch() ?: ['enabled'=>0,'monthly_limit_cents'=>0,'sender_label'=>''];
    return ['accounting_provider'=>$provider,'accounting_connected'=>false,
        'sms_enabled'=>false,'sms_monthly_limit_cents'=>(int)$sms['monthly_limit_cents'],
        'sms_sender_label'=>$sms['sender_label']];
}

/** Reviewable text only. This method has no delivery path or phone-number argument. */
function alpha_create_sms_draft(PDO $db, array $ctx, int $customerId, ?int $jobId, string $message): int {
    if (!in_array($ctx['role'],['owner','admin','staff'],true) || $customerId < 1 || ($jobId !== null && $jobId < 1)
        || trim($message) === '' || mb_strlen($message) > 1600) {
        throw new InvalidArgumentException('Invalid SMS draft.');
    }
    $businessId = (int)$ctx['business_id'];
    if ($jobId !== null) {
        $q = $db->prepare('SELECT id FROM alpha_jobs WHERE business_id=? AND id=? AND customer_id=?');
        $q->execute([$businessId,$jobId,$customerId]);
        if (!$q->fetch()) throw new InvalidArgumentException('Job does not belong to this customer.');
    }
    $q = $db->prepare('INSERT INTO alpha_sms_drafts (business_id,author_id,customer_id,job_id,message) VALUES (?,?,?,?,?)');
    $q->execute([$businessId,(int)$ctx['user_id'],$customerId,$jobId,trim($message)]);
    return (int)$db->lastInsertId();
}

function alpha_sms_drafts(PDO $db, array $ctx): array {
    $q = $db->prepare('SELECT id,customer_id,job_id,message,state,created_at FROM alpha_sms_drafts WHERE business_id=? ORDER BY id DESC LIMIT 100');
    $q->execute([(int)$ctx['business_id']]);
    return $q->fetchAll();
}
