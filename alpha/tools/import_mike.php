<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../src/legacy_import.php';

// Source user should have SELECT access only. Never use Mike's live credentials in Git.
$mode = $argv[1] ?? '';
$businessId = filter_var($argv[2] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!in_array($mode,['plan','apply'],true) || !$businessId || ($mode === 'apply' && ($argv[3] ?? '') !== 'I-UNDERSTAND-THIS-IS-PARTIAL')) {
    fwrite(STDERR,"Usage: php alpha/tools/import_mike.php plan BUSINESS_ID\n       php alpha/tools/import_mike.php apply BUSINESS_ID I-UNDERSTAND-THIS-IS-PARTIAL\n");
    exit(2);
}
$dsn = getenv('EZ_MIKE_READONLY_DSN') ?: '';
$user = getenv('EZ_MIKE_READONLY_USER') ?: '';
$pass = getenv('EZ_MIKE_READONLY_PASSWORD');
if ($dsn === '' || $user === '' || $pass === false) { fwrite(STDERR,"Missing read-only Mike source connection.\n"); exit(2); }
try {
    $source = new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $source->exec("SET time_zone = '+00:00'");
    $target = alpha_db();
    if ((string)$source->query('SELECT DATABASE()')->fetchColumn() === (string)$target->query('SELECT DATABASE()')->fetchColumn()) throw new RuntimeException('Source and target database names must differ.');
    if ($mode === 'plan') {
        alpha_mike_target_check($target,$businessId);
        $report = alpha_mike_inventory($source);
        echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        if ($report['invalid_properties'] || $report['invalid_jobs']) exit(3);
        echo "PLAN ONLY. Deferred tables remain in Mike's source database; this is not a full migration.\n";
    } else {
        $report = alpha_import_mike_core($source,$target,$businessId);
        echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        echo "Core import committed. Source unchanged. Deferred records and file uploads still require migration.\n";
    }
} catch (Throwable $e) { fwrite(STDERR,"IMPORT STOPPED: ".$e->getMessage()."\n"); exit(1); }
