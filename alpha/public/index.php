<?php
declare(strict_types=1);
require_once __DIR__.'/../src/core.php';
require_once __DIR__.'/../src/services.php';
require_once __DIR__.'/../src/catalog.php';
require_once __DIR__.'/../src/integrations.php';
require_once __DIR__.'/../src/legacy_import.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function reply(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
}

try {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($path === '/features' && $method === 'GET') {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'');
        echo alpha_catalog_html(alpha_catalog());
        exit;
    }
    $db = alpha_db();
    alpha_session();
    if ($path === '/session' && $method === 'GET') {
        try {
            $current = alpha_context($db);
            reply(200,['authenticated'=>true,'role'=>$current['role'],'business_id'=>(int)$current['business_id'],'csrf'=>alpha_csrf()]);
        } catch (RuntimeException $e) {
            reply(200,['authenticated'=>false,'csrf'=>alpha_csrf()]);
        }
    }
    if ($path === '/login' && $method === 'POST') {
        alpha_check_csrf((string)($_POST['csrf'] ?? ''));
        // Initial alpha owners are provisioned on the CLI; public sign-up is closed.
        if (!alpha_login($db, (string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''), (int)($_POST['business_id'] ?? 0))) {
            reply(401, ['error'=>'Invalid credentials.']);
        }
        reply(200, ['ok'=>true,'csrf'=>alpha_csrf()]);
    }
    if ($path === '/redeem' && $method === 'POST') {
        alpha_check_csrf((string)($_POST['csrf'] ?? ''));
        alpha_redeem_token($db,(string)($_POST['token'] ?? ''),(string)($_POST['purpose'] ?? ''),(string)($_POST['password'] ?? ''));
        reply(200,['ok'=>true]);
    }
    if ($path === '/logout' && $method === 'POST') {
        alpha_check_csrf((string)($_POST['csrf'] ?? ''));
        $_SESSION = [];
        session_regenerate_id(true);
        reply(200, ['ok'=>true]);
    }
    $ctx = alpha_context($db);
    $businessId = (int)$ctx['business_id'];
    if ($method === 'GET' && $path === '/feedback') reply(200, ['requests'=>alpha_feedback($db,$ctx)]);
    if ($method === 'GET' && preg_match('~^/feedback/([1-9][0-9]*)$~D',(string)$path,$m)) {
        $request = alpha_feedback_detail($db,$ctx,(int)$m[1]);
        if (!$request) reply(404,['error'=>'Not found.']);
        reply(200,['request'=>$request]);
    }
    if ($method === 'GET' && $path === '/ai/usage') reply(200,alpha_usage_summary($db,$businessId));
    if ($method === 'GET' && $path === '/members') reply(200,['current_user_id'=>(int)$ctx['user_id'],'members'=>alpha_members($db,$ctx)]);
    if ($method === 'GET' && $path === '/calendar/drafts') reply(200,['drafts'=>alpha_calendar_drafts($db,$ctx)]);
    if ($method === 'GET' && $path === '/integrations') reply(200,alpha_integration_status($db,$ctx));
    if ($method === 'GET' && $path === '/sms/drafts') reply(200,['drafts'=>alpha_sms_drafts($db,$ctx)]);
    if ($method === 'GET' && $path === '/migration/summary') reply(200,['migration'=>alpha_mike_import_summary($db,$ctx)]);
    if ($method === 'GET' && $path === '/customers') reply(200, ['customers'=>alpha_customers($db,$businessId)]);
    if ($method === 'GET' && $path === '/jobs') reply(200, ['jobs'=>alpha_jobs($db,$businessId)]);
    if ($method === 'GET' && $path === '/properties') {
        reply(200, ['properties'=>alpha_properties($db,$businessId,(int)($_GET['customer_id'] ?? 0))]);
    }
    if ($method === 'GET' && preg_match('~^/customers/([1-9][0-9]*)$~D',(string)$path,$m)) {
        $customer = alpha_customer($db,$businessId,(int)$m[1]);
        if (!$customer) reply(404, ['error'=>'Not found.']);
        reply(200, ['customer'=>$customer]);
    }
    if ($method === 'GET' && preg_match('~^/jobs/([1-9][0-9]*)$~D',(string)$path,$m)) {
        $job = alpha_job($db,$businessId,(int)$m[1]);
        if (!$job) reply(404, ['error'=>'Not found.']);
        reply(200, ['job'=>$job]);
    }
    if ($method === 'POST') {
        alpha_check_csrf((string)($_POST['csrf'] ?? ''));
        if ($path === '/feedback') {
            $id = alpha_submit_feedback($db,$ctx,(string)($_POST['title'] ?? ''),(string)($_POST['detail'] ?? ''),(string)($_POST['area'] ?? ''));
            reply(201,['id'=>$id]);
        }
        if ($path === '/ai/budget') {
            $raw = (string)($_POST['monthly_limit_cents'] ?? '');
            if (!preg_match('/^(0|[1-9][0-9]{0,6})$/D',$raw)) reply(422,['error'=>'Invalid monthly limit.']);
            alpha_set_budget($db,$ctx,(int)$raw);
            reply(200,alpha_usage_summary($db,$businessId));
        }
        if ($path === '/members/disable') {
            $raw = (string)($_POST['user_id'] ?? '');
            if (!preg_match('/^[1-9][0-9]*$/D',$raw)) reply(422,['error'=>'Invalid member.']);
            alpha_disable_member($db,$ctx,(int)$raw);
            reply(200,['ok'=>true]);
        }
        if ($path === '/calendar/drafts') {
            $id = alpha_create_calendar_draft($db,$ctx,(string)($_POST['title'] ?? ''),(string)($_POST['notes'] ?? ''),(string)($_POST['start'] ?? ''),(string)($_POST['end'] ?? ''),(string)($_POST['timezone'] ?? ''));
            reply(201,['id'=>$id]);
        }
        if ($path === '/sms/drafts') {
            $customerId = filter_var($_POST['customer_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $jobRaw = (string)($_POST['job_id'] ?? '');
            $jobId = $jobRaw === '' ? null : filter_var($jobRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if (!$customerId || ($jobRaw !== '' && !$jobId)) reply(422,['error'=>'Choose a customer and an optional job.']);
            $id = alpha_create_sms_draft($db,$ctx,$customerId,$jobId,(string)($_POST['message'] ?? ''));
            reply(201,['id'=>$id,'sent'=>false]);
        }
        if ($path === '/customers') {
            $id = alpha_create_customer($db,$businessId,(string)($_POST['name'] ?? ''));
            reply(201, ['id'=>$id]);
        }
        if ($path === '/properties') {
            $id = alpha_create_property($db,$businessId,(int)($_POST['customer_id'] ?? 0),(string)($_POST['address'] ?? ''));
            reply(201, ['id'=>$id]);
        }
        if ($path === '/jobs') {
            $id = alpha_create_job($db,$businessId,(int)($_POST['customer_id'] ?? 0),(int)($_POST['property_id'] ?? 0),(string)($_POST['title'] ?? ''));
            reply(201, ['id'=>$id]);
        }
    }
    reply(404, ['error'=>'Not found.']);
} catch (InvalidArgumentException $e) {
    reply(422, ['error'=>$e->getMessage()]);
} catch (PDOException $e) {
    error_log('Alpha database request failed: '.$e->getMessage());
    reply(422, ['error'=>'Record could not be saved.']);
} catch (Throwable $e) {
    error_log('Alpha request failed: '.$e->getMessage());
    reply(403, ['error'=>'Access denied.']);
}
