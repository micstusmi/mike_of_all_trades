<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../src/services.php';

// Local, trusted operator only. Never place this file in the web document root.
$action = $argv[1] ?? '';
$db = alpha_db();
try {
    if ($action === 'bootstrap') {
        $name = trim($argv[2] ?? '');
        $email = $argv[3] ?? '';
        if ($name === '' || mb_strlen($name) > 190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Usage: operator.php bootstrap "Business name" owner@example.com');
        $db->beginTransaction();
        try {
            $q = $db->prepare('INSERT INTO alpha_businesses (name) VALUES (?)');
            $q->execute([$name]);
            $id = (int)$db->lastInsertId();
            $token = alpha_issue_token($db,$id,$email,'invite','owner');
            $db->commit();
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
        echo "Business ID: {$id}\nOwner invite token for {$email} (24 hours):\n{$token}\n";
        echo "No data or provider credentials were copied. Deliver token to the intended email only.\n";
    } elseif ($action === 'list') {
        $q = $db->query('SELECT b.id,b.name,b.accounting_provider,p.internal_label FROM alpha_businesses b LEFT JOIN alpha_tester_profiles p ON p.business_id=b.id ORDER BY b.id DESC LIMIT 200');
        foreach ($q as $row) echo json_encode($row,JSON_THROW_ON_ERROR)."\n";
    } elseif ($action === 'label') {
        $id = filter_var($argv[2] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $label = trim($argv[3] ?? '');
        if (!$id || $label === '' || mb_strlen($label) > 190) throw new InvalidArgumentException('Usage: operator.php label BUSINESS_ID "Xero tester"');
        $q = $db->prepare('INSERT INTO alpha_tester_profiles(business_id,internal_label) VALUES (?,?) ON DUPLICATE KEY UPDATE internal_label=VALUES(internal_label)');
        $q->execute([$id,$label]);
        echo "Label saved.\n";
    } elseif ($action === 'provider') {
        $id = filter_var($argv[2] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $provider = $argv[3] ?? '';
        if (!$id || !in_array($provider,['none','zoho','xero','myob','quickbooks'],true)) throw new InvalidArgumentException('Usage: operator.php provider BUSINESS_ID none|zoho|xero|myob|quickbooks');
        $q = $db->prepare('UPDATE alpha_businesses SET accounting_provider=? WHERE id=?');
        $q->execute([$provider,$id]);
        if ($q->rowCount() !== 1) throw new InvalidArgumentException('Business not found or unchanged.');
        echo "Provider profile saved; no accounting connection enabled.\n";
    } elseif ($action === 'feedback') {
        $id = filter_var($argv[2] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) throw new InvalidArgumentException('Usage: operator.php feedback BUSINESS_ID');
        $q = $db->prepare('SELECT id,title,area,status,priority,created_at FROM alpha_feature_requests WHERE business_id=? ORDER BY id DESC LIMIT 100');
        $q->execute([$id]);
        foreach ($q as $row) echo json_encode($row,JSON_THROW_ON_ERROR)."\n";
    } elseif ($action === 'triage') {
        $businessId = filter_var($argv[2] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $requestId = filter_var($argv[3] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$businessId || !$requestId) throw new InvalidArgumentException('Usage: operator.php triage BUSINESS_ID REQUEST_ID STATUS PRIORITY "customer update"');
        alpha_triage_feedback($db,$businessId,$requestId,$argv[4] ?? '',$argv[5] ?? '', $argv[6] ?? '');
        echo "Status saved.\n";
    } elseif ($action === 'invite' || $action === 'reset') {
        $businessId = filter_var($argv[2] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $email = $argv[3] ?? '';
        if (!$businessId || $email === '') throw new InvalidArgumentException('Usage: operator.php invite BUSINESS_ID EMAIL owner|admin|staff; or reset BUSINESS_ID EMAIL');
        $role = $action === 'invite' ? ($argv[4] ?? 'staff') : 'staff';
        echo "Single-use {$action} token for {$email} (24 hours):\n";
        echo alpha_issue_token($db,$businessId,$email,$action,$role)."\n";
        echo "Deliver privately to the verified email address. The token alone grants access; no email delivery is configured yet.\n";
    } else {
        throw new InvalidArgumentException("Commands: bootstrap | list | label | provider | feedback | triage | invite | reset\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR,$e->getMessage()."\n");
    exit(1);
}
