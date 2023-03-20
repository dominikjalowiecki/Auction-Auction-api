<?php

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', FALSE);
ini_set('log_errors', TRUE);
ini_set('html_errors', FALSE);
ini_set('error_log', '');

define('CONFIG', array(
    'DB_HOST' => '',
    'DB_PORT' => '',
    'DB_NAME' => '',
    'DB_USER' => '',
    'DB_PASS' => '',
    'BASE_FRONTEND_URL' => '',
    'BASE_BACKEND_URL' => '',
    'SECRET_KEY' => '',
    'USE_SMTP' => False,
    'SMTP_HOST' => '',
    'SMTP_USERNAME' => '',
    'SMTP_PASSWORD' => '',
    'PAGINATION' => 10,
    'MESSAGES_PAGINATION' => 10
));
