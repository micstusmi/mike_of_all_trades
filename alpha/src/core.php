<?php
declare(strict_types=1);

function alpha_db(): PDO {
    $dsn = getenv('EZ_ALPHA_DSN') ?: '';
    $user = getenv('EZ_ALPHA_DB_USER') ?: '';
    $pass = getenv('EZ_ALPHA_DB_PASSWORD');
    if ($dsn === '' || $user === '' || $pass === false) {
        throw new RuntimeException('Alpha database configuration is missing.');
    }
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec("SET time_zone = '+00:00'");
    return $db;
}

function alpha_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (PHP_SAPI !== 'cli' && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
        throw new RuntimeException('HTTPS is required.');
    }
    session_name('EZALPHA');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => true,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function alpha_login(PDO $db, string $email, string $password, int $businessId): bool {
    $email = mb_strtolower(trim($email));
    if ($businessId < 1 || !filter_var($email,FILTER_VALIDATE_EMAIL)) return false;
    $attempts = $db->prepare('SELECT COUNT(*) FROM alpha_login_attempts WHERE email=? AND business_id=? AND attempted_at>UTC_TIMESTAMP()-INTERVAL 15 MINUTE');
    $attempts->execute([$email,$businessId]);
    if ((int)$attempts->fetchColumn() >= 5) return false;
    $q = $db->prepare('SELECT u.id,u.password_hash FROM alpha_users u JOIN alpha_memberships m ON m.user_id=u.id WHERE u.email=? AND u.email_verified_at IS NOT NULL AND u.disabled_at IS NULL AND m.business_id=? AND m.disabled_at IS NULL LIMIT 1');
    $q->execute([$email, $businessId]);
    $user = $q->fetch();
    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        $failed = $db->prepare('INSERT INTO alpha_login_attempts (email,business_id) VALUES (?,?)');
        $failed->execute([$email,$businessId]);
        return false;
    }
    $clear = $db->prepare('DELETE FROM alpha_login_attempts WHERE email=? AND business_id=?');
    $clear->execute([$email,$businessId]);
    alpha_session();
    session_regenerate_id(true);
    $_SESSION['alpha_user_id'] = (int)$user['id'];
    $_SESSION['alpha_business_id'] = $businessId;
    $_SESSION['alpha_csrf'] = bin2hex(random_bytes(32));
    return true;
}

/** Resolve membership afresh on each request. Never trust a submitted business ID. */
function alpha_context(PDO $db): array {
    alpha_session();
    $userId = (int)($_SESSION['alpha_user_id'] ?? 0);
    $businessId = (int)($_SESSION['alpha_business_id'] ?? 0);
    if ($userId < 1 || $businessId < 1) throw new RuntimeException('Unauthenticated.');
    $q = $db->prepare('SELECT m.business_id,m.user_id,m.role FROM alpha_memberships m JOIN alpha_users u ON u.id=m.user_id WHERE m.user_id=? AND m.business_id=? AND m.disabled_at IS NULL AND u.disabled_at IS NULL AND u.email_verified_at IS NOT NULL');
    $q->execute([$userId,$businessId]);
    $membership = $q->fetch();
    if (!$membership) throw new RuntimeException('Unauthorised business.');
    return $membership;
}

function alpha_csrf(): string {
    alpha_session();
    return (string)($_SESSION['alpha_csrf'] ?? '');
}

function alpha_check_csrf(string $token): void {
    $expected = alpha_csrf();
    if ($expected === '' || !hash_equals($expected, $token)) {
        throw new RuntimeException('Invalid form token.');
    }
}

function alpha_customers(PDO $db, int $businessId): array {
    $q = $db->prepare('SELECT id,name,email,phone FROM alpha_customers WHERE business_id=? ORDER BY id DESC LIMIT 100');
    $q->execute([$businessId]);
    return $q->fetchAll();
}

function alpha_customer(PDO $db, int $businessId, int $customerId): ?array {
    $q = $db->prepare('SELECT id,name,email,phone FROM alpha_customers WHERE business_id=? AND id=?');
    $q->execute([$businessId,$customerId]);
    return $q->fetch() ?: null;
}

function alpha_job(PDO $db, int $businessId, int $jobId): ?array {
    $q = $db->prepare('SELECT id,customer_id,property_id,title,status FROM alpha_jobs WHERE business_id=? AND id=?');
    $q->execute([$businessId,$jobId]);
    return $q->fetch() ?: null;
}

function alpha_properties(PDO $db, int $businessId, int $customerId): array {
    $q = $db->prepare('SELECT id,customer_id,address FROM alpha_properties WHERE business_id=? AND customer_id=? ORDER BY id DESC LIMIT 100');
    $q->execute([$businessId,$customerId]);
    return $q->fetchAll();
}

function alpha_jobs(PDO $db, int $businessId): array {
    $q = $db->prepare('SELECT id,customer_id,property_id,title,status FROM alpha_jobs WHERE business_id=? ORDER BY id DESC LIMIT 100');
    $q->execute([$businessId]);
    return $q->fetchAll();
}

function alpha_create_customer(PDO $db, int $businessId, string $name): int {
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 190) throw new InvalidArgumentException('Invalid customer name.');
    $q = $db->prepare('INSERT INTO alpha_customers(business_id,name) VALUES(?,?)');
    $q->execute([$businessId,$name]);
    return (int)$db->lastInsertId();
}

function alpha_create_property(PDO $db, int $businessId, int $customerId, string $address): int {
    $address = trim($address);
    if ($address === '' || mb_strlen($address) > 500) throw new InvalidArgumentException('Invalid address.');
    $q = $db->prepare('INSERT INTO alpha_properties(business_id,customer_id,address) VALUES(?,?,?)');
    $q->execute([$businessId,$customerId,$address]);
    return (int)$db->lastInsertId();
}

function alpha_create_job(PDO $db, int $businessId, int $customerId, int $propertyId, string $title): int {
    $title = trim($title);
    if ($title === '' || mb_strlen($title) > 190) throw new InvalidArgumentException('Invalid job title.');
    // The composite foreign key also rejects a property from another customer or business.
    $q = $db->prepare('INSERT INTO alpha_jobs(business_id,customer_id,property_id,title) VALUES(?,?,?,?)');
    $q->execute([$businessId,$customerId,$propertyId,$title]);
    return (int)$db->lastInsertId();
}
