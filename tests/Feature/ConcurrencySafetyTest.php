<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ConcurrencySafetyTest extends TestCase
{
    public static function unsafeConnections(): array
    {
        return [
            'no explicit opt in' => [['GLOWBOOK_CONCURRENCY_TEST' => false], 'GLOWBOOK_CONCURRENCY_TEST=1'],
            'application schema' => [['GLOWBOOK_TEST_MYSQL_DATABASE' => 'glowbook_laravel_dev'], 'Only the dedicated database'],
            'SQLite is forbidden' => [['GLOWBOOK_TEST_MYSQL_DATABASE' => ':memory:'], 'Only the dedicated database'],
            'missing dedicated host' => [['GLOWBOOK_TEST_MYSQL_HOST' => false], 'GLOWBOOK_TEST_MYSQL_HOST'],
            'missing dedicated account' => [['GLOWBOOK_TEST_MYSQL_USERNAME' => false], 'GLOWBOOK_TEST_MYSQL_USERNAME'],
        ];
    }

    #[DataProvider('unsafeConnections')]
    public function test_harness_refuses_unsafe_setup_before_bootstrapping_or_migrating(array $overrides, string $message): void
    {
        $root = dirname(__DIR__, 2);
        $command = [PHP_BINARY];
        if (php_ini_loaded_file()) {
            array_push($command, '-c', php_ini_loaded_file());
        }
        array_push($command, $root.'/tests/Support/concurrency.php', '--migrate');
        $environment = array_replace([
            'GLOWBOOK_CONCURRENCY_TEST' => '1', 'GLOWBOOK_TEST_MYSQL_DATABASE' => 'glowbook_test',
            'GLOWBOOK_TEST_MYSQL_HOST' => '127.0.0.1', 'GLOWBOOK_TEST_MYSQL_USERNAME' => 'test-only',
            'GLOWBOOK_TEST_MYSQL_PASSWORD' => false,
        ], $overrides);
        $process = new Process($command, $root, $environment, null, 10);
        $process->run();
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString($message, $process->getErrorOutput());
        $this->assertSame('', $process->getOutput());
    }
}
