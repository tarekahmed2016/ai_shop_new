<?php

namespace Tests\Support\Concurrency;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Shared-file SQLite (or optional dedicated MariaDB) so forked workers
 * see committed parent state. :memory: cannot prove overlapping writes.
 */
final class ConcurrencyEnvironment
{
    public string $workDir;

    public string $databasePath;

    public string $cachePath;

    public string $driver = 'sqlite';

    public function __construct(?string $workDir = null)
    {
        $this->workDir = $workDir ?? sys_get_temp_dir().'/ai_shop_c6_'.str_replace('.', '_', uniqid('', true));
        $this->databasePath = $this->workDir.'/database.sqlite';
        $this->cachePath = $this->workDir.'/cache';
    }

    public function prepareDirectories(): void
    {
        if (! is_dir($this->workDir)) {
            mkdir($this->workDir, 0777, true);
        }

        if (! is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }

        if (! is_file($this->databasePath)) {
            touch($this->databasePath);
        }
    }

    public function apply(Application $app): void
    {
        $this->prepareDirectories();
        $this->driver = $this->requestedDriver();

        $app['config']->set('cache.default', 'file');
        $app['config']->set('cache.stores.file.path', $this->cachePath);
        $app['config']->set('queue.default', 'sync');
        $app->forgetInstance('cache');
        $app->forgetInstance('cache.store');
        Cache::clearResolvedInstances();

        if ($this->driver === 'sqlite') {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite.database', $this->databasePath);
            $app['config']->set('database.connections.sqlite.busy_timeout', 10000);
            $app['config']->set('database.connections.sqlite.journal_mode', 'wal');
            $app['config']->set('database.connections.sqlite.foreign_key_constraints', true);
            DB::purge('sqlite');

            return;
        }

        $database = $this->requiredEnv('CONCURRENCY_DB_DATABASE', 'ai_shop_concurrency_test');
        if (! str_contains(strtolower($database), 'test')) {
            throw new RuntimeException('Refusing concurrency suite against a database whose name does not contain "test".');
        }

        $connection = $this->driver;
        $app['config']->set('database.default', $connection);
        $app['config']->set("database.connections.{$connection}.host", $this->env('CONCURRENCY_DB_HOST', '127.0.0.1'));
        $app['config']->set("database.connections.{$connection}.port", $this->env('CONCURRENCY_DB_PORT', '3306'));
        $app['config']->set("database.connections.{$connection}.database", $database);
        $app['config']->set("database.connections.{$connection}.username", $this->env('CONCURRENCY_DB_USERNAME', 'root'));
        $app['config']->set("database.connections.{$connection}.password", $this->env('CONCURRENCY_DB_PASSWORD', ''));
        $app['config']->set("database.connections.{$connection}.unix_socket", $this->env('CONCURRENCY_DB_SOCKET', ''));
        DB::purge($connection);
    }

    public function usesRowLocks(): bool
    {
        return in_array($this->driver, ['mysql', 'mariadb'], true);
    }

    public function cleanup(): void
    {
        if (! is_dir($this->workDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->workDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        @rmdir($this->workDir);
    }

    public static function requestedDriver(): string
    {
        $driver = strtolower(self::readEnv('CONCURRENCY_DB', 'sqlite'));

        return in_array($driver, ['mysql', 'mariadb'], true) ? $driver : 'sqlite';
    }

    private function env(string $key, string $default): string
    {
        return self::readEnv($key, $default);
    }

    private static function readEnv(string $key, string $default): string
    {
        $candidates = [
            getenv($key),
            $_ENV[$key] ?? false,
            $_SERVER[$key] ?? false,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    private function requiredEnv(string $key, string $default): string
    {
        return $this->env($key, $default);
    }
}
