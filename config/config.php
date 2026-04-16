<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'name' => $_ENV['DB_NAME'] ?? 'reward_system',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASS'] ?? '',
    ],
    'app' => [
        'name'    => $_ENV['APP_NAME'] ?? 'RewardHub',
        'url'     => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
        'env'     => $_ENV['APP_ENV'] ?? 'production',
        'debug'   => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'secret'  => $_ENV['APP_SECRET'] ?? 'change_this_to_a_random_secret_key_32chars',
    ],
    'mail' => [
        'host'     => $_ENV['MAIL_HOST'] ?? 'smtp.mailtrap.io',
        'port'     => (int)($_ENV['MAIL_PORT'] ?? 587),
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'from'     => $_ENV['MAIL_FROM'] ?? 'noreply@rewardhub.com',
        'from_name'=> $_ENV['MAIL_FROM_NAME'] ?? 'RewardHub',
    ],
    'recaptcha' => [
        'site_key'   => $_ENV['RECAPTCHA_SITE_KEY'] ?? '',
        'secret_key' => $_ENV['RECAPTCHA_SECRET_KEY'] ?? '',
    ],
    'session' => [
        'lifetime' => 7200,
        'name'     => 'REWARDHUB_SESSION',
    ],
];
