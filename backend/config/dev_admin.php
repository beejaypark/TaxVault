<?php

return [
    'enabled' => env('DEV_ADMIN_LOGIN_ENABLED', false),
    'login' => env('DEV_ADMIN_LOGIN') ?: env('KEYCLOAK_ADMIN', 'admin'),
    'password' => env('DEV_ADMIN_PASSWORD') ?: env('KEYCLOAK_ADMIN_PASSWORD'),
];