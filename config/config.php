<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

date_default_timezone_set('Europe/Paris');

return [
    'database' => [
        'host' => $_SERVER['DbUrl'] ?? 'localhost',
        'port' => $_SERVER['DbPort'] ?? 3306,
        'name' => $_SERVER['DbName'] ?? 'spreadly',
        'user' => $_SERVER['DbUser'] ?? 'root',
        'password' => $_SERVER['DbPassword'] ?? '',
        'prefix' => $_SERVER['DbPrefix'] ?? '',
    ],

    'email' => [
        'brevo_api_key' => $_SERVER['BrevoApiKey'] ?? '',
        'mail_from_email' => $_SERVER['MailFromEmail'] ?? '',
        'mail_from_name' => $_SERVER['MailFromName'] ?? '',
        'mail_subject' => $_SERVER['MailSubject'] ?? 'Your love messages',
    ],

    'app' => [
        'end_date' => $_SERVER['EndDate'] ?? '2024-12-31 23:59:59',
        'theme' => $_SERVER['Theme'] ?? 'christmas',
    ],

    'stripe' => [
        'secret_key' => $_SERVER['StripeSecretKey'] ?? '',
        'publishable_key' => $_SERVER['StripePublishableKey'] ?? '',
    ],
];
