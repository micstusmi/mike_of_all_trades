<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function normalisePhoneNumber(string $phone): string
{
    return preg_replace('/[^0-9+]/', '', trim($phone));
}

function tripMemberIsLoggedIn(): bool
{
    return isset(
        $_SESSION['trip_member_id'],
        $_SESSION['trip_id'],
        $_SESSION['trip_member_name']
    );
}

function requireTripLogin(): void
{
    if (!tripMemberIsLoggedIn()) {
        header('Location: /mike_of_all_trades/thailand-trip/access.php');
        exit;
    }
}

function requireTripAdmin(): void
{
    requireTripLogin();

    if (($_SESSION['trip_role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}