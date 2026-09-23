<?php
declare(strict_types=1);
require_once __DIR__.'/../src/core.php';
require_once __DIR__.'/../src/services.php';

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

    $newEmail = 'new-'.bin2hex(random_bytes(8)).'@example.invalid';
    $inviteToken = alpha_issue_token($db,$businessA,$newEmail,'invite','staff');
    alpha_redeem_token($db,$inviteToken,'invite','a long disposable password');
    try { alpha_redeem_token($db,$inviteToken,'invite','another long password'); throw new RuntimeException('Invite reused'); }
    catch (InvalidArgumentException $e) { /* One-time token. */ }
    $q = $db->prepare('SELECT u.id FROM alpha_users u JOIN alpha_memberships m ON m.user_id=u.id WHERE u.email=? AND m.business_id=? AND m.role=? AND u.email_verified_at IS NOT NULL');
    $q->execute([$newEmail,$businessA,'staff']);
    ensure((bool)$q->fetch(),'Invite did not create verified scoped membership');

    $customerA = alpha_create_customer($db,$businessA,'Only A');
    $customerB = alpha_create_customer($db,$businessB,'Only B');
    $propertyA = alpha_create_property($db,$businessA,$customerA,'Test address A');
    $propertyB = alpha_create_property($db,$businessB,$customerB,'Test address B');
    $jobA = alpha_create_job($db,$businessA,$customerA,$propertyA,'Only A job');
    $jobB = alpha_create_job($db,$businessB,$customerB,$propertyB,'Only B job');

    $contextA = ['business_id'=>$businessA,'user_id'=>$userA,'role'=>'staff'];
    $contextB = ['business_id'=>$businessB,'user_id'=>$userB,'role'=>'staff'];
    $requestA = alpha_submit_feedback($db,$contextA,'A request','A private description','job');
    $requestB = alpha_submit_feedback($db,$contextB,'B request','B private description','job');
    ensure(alpha_feedback_detail($db,$contextA,$requestB) === null,'A read B feedback');
    ensure(alpha_feedback_detail($db,$contextB,$requestA) === null,'B read A feedback');
    ensure(count(alpha_feedback($db,$contextA)) === 1,'A feedback list escaped tenant');
    try { alpha_triage_feedback($db,$businessA,$requestB,'planned','medium','leak'); throw new RuntimeException('Cross-business triage accepted'); }
    catch (InvalidArgumentException $e) { /* Tenant-owned ID was not found. */ }
    alpha_triage_feedback($db,$businessA,$requestA,'assessing','medium','Reviewing this.');
    ensure(count(alpha_feedback_detail($db,$contextA,$requestA)['updates']) === 1,'A update missing');

    $ownerA = ['business_id'=>$businessA,'user_id'=>$userA,'role'=>'owner'];
    alpha_set_budget($db,$ownerA,100);
    $q = $db->prepare('UPDATE alpha_ai_budgets SET monthly_provider_limit_microusd=10000 WHERE business_id=?');
    $q->execute([$businessA]);
    ensure(alpha_record_usage($db,$businessA,$userA,'voice','test-a',1.0,'requests',60,1000),'A usage not recorded');
    ensure(!alpha_record_usage($db,$businessA,$userA,'voice','test-a',1.0,'requests',60,1000),'Duplicate usage charged');
    ensure(alpha_usage_summary($db,$businessA)['spent_cents'] === 60,'A usage total incorrect');
    ensure(alpha_usage_summary($db,$businessB)['spent_cents'] === 0,'B saw A usage');
    try { alpha_record_usage($db,$businessA,$userA,'voice','test-b',1.0,'requests',60,1000); throw new RuntimeException('Budget overrun accepted'); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'Monthly AI limit reached.') throw $e; }
    try { alpha_set_budget($db,$contextA,10000); throw new RuntimeException('Staff changed budget'); }
    catch (InvalidArgumentException $e) { /* Only owner may set limit. */ }
    try { alpha_record_usage($db,$businessA,$userB,'voice','foreign-user',1.0,'requests',1,1000); throw new RuntimeException('Foreign user billed'); }
    catch (PDOException $e) { /* Composite membership FK rejects cross-business user. */ }

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
    echo "PASS: two-business records, membership, feedback and AI usage isolation. All fixtures rolled back.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR,"FAIL: ".$e->getMessage()."\n");
    exit(1);
}
