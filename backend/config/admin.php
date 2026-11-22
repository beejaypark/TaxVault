<?php

return [
    'notification_email' => env('TAXVAULT_ADMIN_NOTIFICATION_EMAIL'),
    'emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('TAXVAULT_ADMIN_EMAILS', ''))))),
];
