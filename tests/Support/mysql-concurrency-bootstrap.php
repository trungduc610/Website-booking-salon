<?php

use Illuminate\Support\Facades\DB;

// Never inherit the application's .env, cached config, URL, socket or credentials.
if (getenv('GLOWBOOK_CONCURRENCY_TEST') !== '1') {
    throw new ConcurrencyTestFailure('Set GLOWBOOK_CONCURRENCY_TEST=1 to opt into the dedicated MySQL test.');
}
if (getenv('GLOWBOOK_TEST_MYSQL_DATABASE') !== 'glowbook_test') {
    throw new ConcurrencyTestFailure('Only the dedicated database glowbook_test is allowed.');
}
foreach (['HOST', 'USERNAME'] as $key) {
    if (! getenv('GLOWBOOK_TEST_MYSQL_'.$key)) {
        throw new ConcurrencyTestFailure('Set the explicit test connection variable GLOWBOOK_TEST_MYSQL_'.$key.'.');
    }
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$scratch = sys_get_temp_dir().'/glowbook-concurrency-'.bin2hex(random_bytes(12));
mkdir($scratch, 0700, true);
register_shutdown_function(function () use ($scratch): void {
    rmdir($scratch);
});
$environment = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_CONFIG_CACHE' => $scratch.'/config.php', 'APP_ROUTES_CACHE' => $scratch.'/routes.php',
    'APP_EVENTS_CACHE' => $scratch.'/events.php',
    'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'glowbook_test',
    'DB_HOST' => getenv('GLOWBOOK_TEST_MYSQL_HOST'),
    'DB_PORT' => getenv('GLOWBOOK_TEST_MYSQL_PORT') ?: '3306',
    'DB_USERNAME' => getenv('GLOWBOOK_TEST_MYSQL_USERNAME'),
    'DB_PASSWORD' => getenv('GLOWBOOK_TEST_MYSQL_PASSWORD') === false ? '' : getenv('GLOWBOOK_TEST_MYSQL_PASSWORD'),
    'DB_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'single',
];
foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($scratch);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['config']->set('logging.default', 'concurrency');
$app['config']->set('logging.channels.concurrency', ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class]);
$connection = DB::connection();
if (! $app->environment('testing') || $app->configurationIsCached()
    || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'glowbook_test'
    || DB::selectOne('SELECT DATABASE() AS db')->db !== 'glowbook_test') {
    throw new ConcurrencyTestFailure('Refusing to use a connection outside the dedicated MySQL test database.');
}
$version = DB::selectOne('SELECT VERSION() AS version')->version;
if (! preg_match('/^8\./', $version) || stripos($version, 'MariaDB') !== false) {
    throw new ConcurrencyTestFailure('This harness requires MySQL 8 and performance_schema.data_lock_waits.');
}

return $app;
