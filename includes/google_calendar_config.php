<?php
declare(strict_types=1);

$isLocal = in_array(
    $_SERVER['HTTP_HOST'] ?? '',
    ['localhost', '127.0.0.1'],
    true
);

if ($isLocal) {
    define(
        'GOOGLE_CALENDAR_CREDENTIALS_PATH',
        '/Applications/XAMPP/private/google-calendar-service-account.json'
    );
} else {
    define(
        'GOOGLE_CALENDAR_CREDENTIALS_PATH',
        '/home/admin/private/google-calendar-service-account.json'
    );
}

define(
    'GOOGLE_AVAILABILITY_CALENDAR_ID',
    'a7a4ea0156c0f0919ddf9ed1e57006fc0d4658173a2ea7605b51304aa53c2cdb@group.calendar.google.com'
);

define(
    'GOOGLE_MAIN_CALENDAR_ID',
    'advancedgroupptyltd@gmail.com'
);

define(
    'GOOGLE_CALENDAR_TIMEZONE',
    'Australia/Melbourne'
);

define(
    'GOOGLE_CALENDAR_ENABLED',
    true
);
