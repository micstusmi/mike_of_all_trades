<?php

/*
 * Thailand trip email helper.
 *
 * It first tries the site's existing PHPMailer/SMTP configuration.
 * If that is unavailable, it falls back to PHP mail().
 *
 * Registration must never fail merely because email delivery fails.
 */

function tripEmailConfigValue(
    array $names,
    mixed $default = null
): mixed {
    foreach ($names as $name) {
        if (defined($name)) {
            return constant($name);
        }

        if (isset($GLOBALS[$name])) {
            return $GLOBALS[$name];
        }
    }

    return $default;
}

function loadTripEmailDependencies(): void
{
    $configPaths = [
        __DIR__ . '/../../includes/config.php',
        __DIR__ . '/../../includes/email_config.php'
    ];

    foreach ($configPaths as $configPath) {
        if (is_file($configPath)) {
            require_once $configPath;
        }
    }

    if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        return;
    }

    $autoloadPaths = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../PHPMailer/vendor/autoload.php',
        __DIR__ . '/../../includes/PHPMailer/vendor/autoload.php'
    ];

    foreach ($autoloadPaths as $autoloadPath) {
        if (is_file($autoloadPath)) {
            require_once $autoloadPath;

            if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                return;
            }
        }
    }

    $phpMailerPaths = [
        __DIR__ . '/../../PHPMailer/src',
        __DIR__ . '/../../includes/PHPMailer/src'
    ];

    foreach ($phpMailerPaths as $basePath) {
        if (
            is_file($basePath . '/PHPMailer.php')
            && is_file($basePath . '/SMTP.php')
            && is_file($basePath . '/Exception.php')
        ) {
            require_once $basePath . '/Exception.php';
            require_once $basePath . '/PHPMailer.php';
            require_once $basePath . '/SMTP.php';

            return;
        }
    }
}

function sendTripEmail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $plainBody
): bool {
    $toEmail = trim($toEmail);

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log(
            'Thailand trip email skipped: invalid recipient email.'
        );

        return false;
    }

    loadTripEmailDependencies();

    $fromEmail = (string) tripEmailConfigValue([
        'TRIP_FROM_EMAIL',
        'SMTP_FROM_EMAIL',
        'MAIL_FROM_EMAIL',
        'FROM_EMAIL',
        'SMTP_USERNAME',
        'SMTP_USER'
    ], '');

    $fromName = (string) tripEmailConfigValue([
        'TRIP_FROM_NAME',
        'SMTP_FROM_NAME',
        'MAIL_FROM_NAME',
        'FROM_NAME'
    ], 'Thailand 2027 Trip');

    $smtpHost = (string) tripEmailConfigValue([
        'SMTP_HOST',
        'MAIL_HOST'
    ], '');

    $smtpUsername = (string) tripEmailConfigValue([
        'SMTP_USERNAME',
        'SMTP_USER',
        'MAIL_USERNAME'
    ], '');

    $smtpPassword = (string) tripEmailConfigValue([
        'SMTP_PASSWORD',
        'SMTP_PASS',
        'MAIL_PASSWORD'
    ], '');

    $smtpPort = (int) tripEmailConfigValue([
        'SMTP_PORT',
        'MAIL_PORT'
    ], 587);

    $smtpEncryption = (string) tripEmailConfigValue([
        'SMTP_ENCRYPTION',
        'MAIL_ENCRYPTION'
    ], 'tls');

    if (
        class_exists('\PHPMailer\PHPMailer\PHPMailer')
        && $smtpHost !== ''
        && $smtpUsername !== ''
        && $smtpPassword !== ''
    ) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->Port = $smtpPort;

            if ($smtpEncryption !== '') {
                $mail->SMTPSecure = $smtpEncryption;
            }

            $effectiveFromEmail =
                filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
                    ? $fromEmail
                    : $smtpUsername;

            $mail->setFrom(
                $effectiveFromEmail,
                $fromName
            );

            $mail->addAddress(
                $toEmail,
                $toName
            );

            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody;

            $mail->send();

            return true;

        } catch (Throwable $e) {
            error_log(
                'Thailand trip SMTP email failed: '
                . $e->getMessage()
            );

            return false;
        }
    }

    /*
     * Basic fallback for servers with a configured mail transport.
     */
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        error_log(
            'Thailand trip email failed: no valid sender configuration.'
        );

        return false;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail
    ];

    $sent = mail(
        $toEmail,
        $subject,
        $htmlBody,
        implode("\r\n", $headers)
    );

    if (!$sent) {
        error_log(
            'Thailand trip PHP mail() delivery failed.'
        );
    }

    return $sent;
}

function tripOrganiserEmail(): string
{
    return (string) tripEmailConfigValue([
        'TRIP_ORGANISER_EMAIL',
        'TRIP_ADMIN_EMAIL',
        'ADMIN_EMAIL',
        'SMTP_FROM_EMAIL',
        'MAIL_FROM_EMAIL',
        'FROM_EMAIL'
    ], '');
}
