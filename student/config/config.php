<?php
// student/config/config.php

// Load environment variables
require_once __DIR__ . '/../../includes/env.php';
load_env(__DIR__ . '/../../includes/.env');

// Site URL
define('SITE_URL', 'http://localhost/sms/student/');

// Email Configuration — galing sa .env
define('SMTP_HOST',       getenv('SMTP_HOST')       ?: 'smtp.gmail.com');
define('SMTP_PORT',       (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME',   getenv('SMTP_USERNAME')   ?: '');
define('SMTP_PASSWORD',   getenv('SMTP_PASSWORD')   ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: getenv('SMTP_USERNAME'));
define('SMTP_FROM_NAME',  getenv('SMTP_FROM_NAME')  ?: 'Student Management System');

// Password Reset Settings
define('TOKEN_LIFETIME', 3600);
define('MAX_RESET_ATTEMPTS', 3);