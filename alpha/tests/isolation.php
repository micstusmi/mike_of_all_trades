<?php
declare(strict_types=1);
require_once __DIR__.'/../src/core.php';

// Run only against a throwaway database with alpha/schema/001_foundation.sql installed.
if (getenv('EZ_ALPHA_TEST_DATABASE') !== 'YES') {
    fwrite(STDERR, "Set EZ_ALPHA_TEST_DATABASE=YES for a disposable alpha test database.\n");
    exit(1);
}

function ensure(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$db = alpha_db();
$db->beginTransaction();
try {
    $db->exec("INSERT INTO alpha_businesses(name) VALUES('Alpha test A')");
    $businessA = (int)$db->lastInsertId();
    $db->exec("INSERT INTO alpha_businesses(name) VALUES('Alpha test B')");
    $businessB = (int)$db->lastInsertId();
    $password = password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT);
    $insertUser = $db->prepare('INSERT INTO alpha_users(email,password_hash,email_verified_at) VALUES(?,?,NOW())');
    $insertUser->execute(['test-a-'.bin2hex(random_bytes(8)).'@example.invalid',$password]);
    $userA = (int)$db->lastInsertId();
    $insertUser->execute(['test-b-'.bin2hex(random_bytes(8)).'@example.invalid',$password]);
    $userB = (int)$db->lastInsertId();
    $member = $db->prepare("INSERT INTO alpha_memberships(business_id,user_id,role) VALUES(?,?,'owner')");
    $member->execute([$businessA,$userA]);
    $member->execute([$businessB,$userB]);

    $customerA = alpha_create_customer($db,$businessA,'Only A');
    $customerB = alpha_create_customer($db,$businessB,'Only B');
    $propertyA = alpha_create_property($db,$businessA,$customerA,'Test address A');
    $propertyB = alpha_create_property($db,$businessB,$customerB,'Test address B');
    $jobA = alpha_create_job($db,$businessA,$customerA,$propertyA,'Only A job');
    $jobB = alpha_create_job($db,$businessB,$customerB,$propertyB,'Only B job');

    ensure(alpha_customer($db,$businessA,$customerB) === null,'A read B customer');
    ensure(alpha_job($db,$businessA,$jobB) === null,'A read B job');
    ensure(alpha_customer($db,$businessB,$customerA) === null,'B read A customer');
    ensure(alpha_job($db,$businessB,$jobA) === null,'B read A job');
    ensure(count(alpha_customers($db,$businessA)) === 1,'A customer list escaped tenant');
    ensure(count(alpha_jobs($db,$businessB)) === 1,'B job list escaped tenant');
    ensure(count(alpha_properties($db,$businessA,$customerB)) === 0,'A read B properties');

    alpha_session();
    $_SESSION['alpha_user_id'] = $userA;
    $_SESSION['alpha_business_id'] = $businessA;
    ensure((int)alpha_context($db)['business_id'] === $businessA,'A membership invalid');
    $_SESSION['alpha_business_id'] = $businessB;
    try { alpha_context($db); throw new RuntimeException('A selected B without membership'); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'Unauthorised business.') throw $e; }

    // A forged job creation cannot pair A with a property owned by B.
    try { alpha_create_job($db,$businessA,$customerA,$propertyB,'Forged'); throw new RuntimeException('Foreign property accepted'); }
    catch (PDOException $e) { /* Expected composite foreign-key rejection. */ }
    try { alpha_create_property($db,$businessA,$customerB,'Forged'); throw new RuntimeException('Foreign customer accepted'); }
    catch (PDOException $e) { /* Expected composite foreign-key rejection. */ }

    $db->rollBack();
    echo "PASS: two-business read, list, membership and write isolation. All fixtures rolled back.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR,"FAIL: ".$e->getMessage()."\n");
    exit(1);
}
