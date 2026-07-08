<?php

/**
 * Application Configuration
 *
 * Returns the configuration array for the Handlr application. Configuration values
 * are loaded from environment variables with sensible defaults for local development.
 *
 * This file is loaded by the bootstrap via Handlr\Config\Loader and the values
 * are made available through the DI container.
 *
 * @example Loading config through the Loader
 * ```php
 * use Handlr\Config\Loader;
 *
 * $config = Loader::load(HANDLR_APP_APP_PATH . '/config.php', $container);
 * $dsn = $config['database']['dsn'];
 * ```
 *
 * @example Accessing config values from the app
 * ```php
 * $app = handlr_app();
 * $isDebug = $app['config']['app']['debug'];
 * ```
 *
 * @return array{
 *     app: array{env: string, debug: bool},
 *     database: array{dsn: string, user: string, password: string, options: array}
 * }
 */
return [
    'app' => [
        'env' => $_ENV['APP_ENV'] ?? 'local',
        'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
        // Service providers run during bootstrap. Order matters: each provider's
        // register() phase runs in declared order, then every boot() runs in
        // declared order. To disable a module, comment its provider out.
        'providers' => [
            App\Auth\AuthServiceProvider::class,
            App\Profile\ProfileServiceProvider::class,
        ],
    ],
    'mail' => [
        'transport' => $_ENV['MAIL_TRANSPORT'] ?? 'smtp',
        'host' => $_ENV['MAIL_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['MAIL_PORT'] ?? 1025,
        'region' => $_ENV['MAIL_REGION'] ?? 'us-east-1',
        'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Handlr App',
    ],
    'database' => [
        'dsn' => $_ENV['DB_DSN'],
        'user' => $_ENV['DB_USER'],
        'password' => $_ENV['DB_PASSWORD'],
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
];
