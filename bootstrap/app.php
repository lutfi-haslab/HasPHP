<?php

use Hasphp\App\Core\Application;
use Hasphp\App\Core\DB\DatabaseManager;
use Hasphp\App\Core\DB\Drivers\DatabaseDriver;
use Hasphp\App\Providers\DatabaseServiceProvider;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new HasPHP application instance
| which serves as the "glue" for all the components of HasPHP, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton('db', function ($app) {
    return new DatabaseManager([
        'default' => $_ENV['DB_CONNECTION'] ?? 'sqlite',
        'connections' => [
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => $_ENV['DB_DATABASE'] ?? $app->databasePath('database.sqlite'),
                'timeout' => (int) ($_ENV['DB_TIMEOUT'] ?? 60),
            ],
            'mysql' => [
                'driver' => 'mysql',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                'database' => $_ENV['DB_DATABASE'] ?? 'hasphp',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
                'database' => $_ENV['DB_DATABASE'] ?? 'hasphp',
                'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8',
                'schema' => $_ENV['DB_SCHEMA'] ?? 'public',
            ],
        ],
    ]);
});

// Bind the default database connection
$app->singleton(DatabaseDriver::class, function ($app) {
    return $app->resolve('db')->connection();
});

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| The service providers listed here will be automatically loaded on the
| request to your application. Feel free to add your own services to
| this array to grant expanded functionality to your applications.
|
*/

// Register core service providers here as they are created

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
