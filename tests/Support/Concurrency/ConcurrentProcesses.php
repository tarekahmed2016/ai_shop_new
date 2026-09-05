<?php

namespace Tests\Support\Concurrency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Overlapping workers via pcntl_fork + a file barrier.
 *
 * This clones the current PHP CLI process. On a memory-constrained host that
 * means at most two workers, waited and reaped before return, then hard-killed
 * so Pest/PHPUnit shutdown handlers cannot continue the suite in a child.
 */
final class ConcurrentProcesses
{
    private const int MAX_WORKERS = 2;

    private const string WORKER_ENV = 'AI_SHOP_C6_WORKER';

    /**
     * @var list<int>
     */
    private static array $activePids = [];

    public static function supported(): bool
    {
        return function_exists('pcntl_fork')
            && function_exists('pcntl_waitpid')
            && function_exists('posix_kill');
    }

    /**
     * @param  callable(int $index): mixed  $work
     * @return list<array{ok: bool, value: mixed, error: ?string}>
     */
    public static function map(int $workers, callable $work, int $barrierTimeoutMs = 8000): array
    {
        if ($workers !== self::MAX_WORKERS) {
            throw new RuntimeException('Concurrency tests must use exactly two overlapping workers on this host.');
        }

        if (! self::supported()) {
            throw new RuntimeException('pcntl_fork is required for overlapping concurrency tests.');
        }

        if (getenv(self::WORKER_ENV) === '1' || self::$activePids !== []) {
            throw new RuntimeException('Nested or overlapping concurrency forks are not allowed.');
        }

        $dir = sys_get_temp_dir().'/ai_shop_c6_barrier_'.str_replace('.', '_', uniqid('', true));
        mkdir($dir.'/ready', 0777, true);
        mkdir($dir.'/results', 0777, true);
        $goFile = $dir.'/go';

        DB::disconnect();
        DB::purge();

        $pids = [];

        try {
            for ($i = 0; $i < $workers; $i++) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    self::killAndReap($pids);
                    throw new RuntimeException('pcntl_fork failed.');
                }

                if ($pid === 0) {
                    self::$activePids = [];
                    putenv(self::WORKER_ENV.'=1');
                    $_ENV[self::WORKER_ENV] = '1';
                    self::runWorker($i, $dir, $goFile, $work, $barrierTimeoutMs);
                }

                $pids[] = $pid;
                self::$activePids = $pids;
            }

            self::waitUntil(function () use ($dir, $workers) {
                return count(glob($dir.'/ready/*') ?: []) >= $workers;
            }, $barrierTimeoutMs, 'workers did not reach the start barrier');

            file_put_contents($goFile, '1');

            $joinTimeoutMs = $barrierTimeoutMs + 15000;
            self::waitAndReap($pids, $joinTimeoutMs);
        } catch (Throwable $exception) {
            self::killAndReap($pids);
            self::removeDir($dir);
            DB::reconnect();
            throw $exception;
        } finally {
            self::$activePids = [];
        }

        DB::reconnect();

        $results = [];
        for ($i = 0; $i < $workers; $i++) {
            $path = $dir.'/results/'.$i;
            $results[] = is_file($path)
                ? unserialize((string) file_get_contents($path), ['allowed_classes' => true])
                : ['ok' => false, 'value' => null, 'error' => 'worker produced no result'];
        }

        self::removeDir($dir);

        return $results;
    }

    /**
     * @param  list<array{ok: bool, value: mixed, error: ?string}>  $results
     * @return list<mixed>
     */
    public static function values(array $results): array
    {
        return array_map(fn (array $row) => $row['value'], $results);
    }

    /**
     * @param  list<array{ok: bool, value: mixed, error: ?string}>  $results
     */
    public static function assertAllOk(array $results): void
    {
        $errors = [];
        foreach ($results as $index => $result) {
            if (! ($result['ok'] ?? false)) {
                $errors[] = 'worker '.$index.': '.($result['error'] ?? 'unknown failure');
            }
        }

        if ($errors !== []) {
            throw new RuntimeException("Concurrent workers failed:\n".implode("\n", $errors));
        }
    }

    /**
     * @param  callable(int $index): mixed  $work
     */
    private static function runWorker(int $index, string $dir, string $goFile, callable $work, int $barrierTimeoutMs): never
    {
        try {
            self::reconnectInChild();
            file_put_contents($dir.'/ready/'.$index, '1');
            self::waitForFile($goFile, $barrierTimeoutMs);
            $value = $work($index);
            file_put_contents($dir.'/results/'.$index, serialize([
                'ok' => true,
                'value' => $value,
                'error' => null,
            ]));
        } catch (Throwable $exception) {
            file_put_contents($dir.'/results/'.$index, serialize([
                'ok' => false,
                'value' => null,
                'error' => $exception::class.': '.$exception->getMessage(),
            ]));
        }

        try {
            DB::disconnect();
            DB::purge();
        } catch (Throwable) {
        }

        self::exitWorker();
    }

    private static function exitWorker(): never
    {
        // Prevent Pest/PHPUnit from continuing the suite inside the child.
        posix_kill(getmypid(), SIGKILL);
        exit(0);
    }

    private static function reconnectInChild(): void
    {
        DB::purge();
        DB::reconnect();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA busy_timeout = 10000');
            DB::statement('PRAGMA journal_mode = WAL');
            DB::statement('PRAGMA foreign_keys = ON');
        }

        Cache::forgetDriver(config('cache.default'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<int>  $pids
     */
    private static function waitAndReap(array $pids, int $timeoutMs): void
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);
        $remaining = array_fill_keys($pids, true);

        while ($remaining !== []) {
            if (microtime(true) > $deadline) {
                self::killAndReap(array_map('intval', array_keys($remaining)));
                throw new RuntimeException('concurrent workers exceeded join timeout');
            }

            foreach (array_keys($remaining) as $pid) {
                $waited = pcntl_waitpid($pid, $status, WNOHANG);
                if ($waited === $pid || $waited === -1) {
                    unset($remaining[$pid]);
                }
            }

            if ($remaining !== []) {
                usleep(5000);
            }
        }
    }

    /**
     * @param  list<int>  $pids
     */
    private static function killAndReap(array $pids): void
    {
        foreach ($pids as $pid) {
            if ($pid > 0 && ! self::pidGone($pid)) {
                posix_kill($pid, SIGKILL);
            }
        }

        foreach ($pids as $pid) {
            if ($pid <= 0) {
                continue;
            }

            do {
                $waited = pcntl_waitpid($pid, $status);
            } while ($waited === -1 && pcntl_get_last_error() === PCNTL_EINTR);

            if (self::pidGone($pid)) {
                continue;
            }
        }
    }

    private static function pidGone(int $pid): bool
    {
        if ($pid <= 0) {
            return true;
        }

        return ! posix_kill($pid, 0);
    }

    private static function waitForFile(string $path, int $timeoutMs): void
    {
        self::waitUntil(fn () => is_file($path), $timeoutMs, 'start barrier was never released');
    }

    private static function waitUntil(callable $predicate, int $timeoutMs, string $message): void
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);

        while (! $predicate()) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException($message);
            }
            usleep(1000);
        }
    }

    private static function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        @rmdir($dir);
    }
}
