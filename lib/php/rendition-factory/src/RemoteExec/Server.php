<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * convert-server: listens on the network shared with the workers only (no authentication: the
 * network is the boundary, a token would be one more secret), forks one process per connection,
 * at most $maxJobs at once, each with its own uid of the pool ($firstUid, $firstUid + 1…).
 *
 * Runs as root, with the capabilities to change owners and uids and to kill (see the
 * databox-converter service), without any secret nor any access to the data services.
 */
final class Server
{
    /** uids created in the image of the conversion container */
    public const int POOL_SIZE = 32;
    public const int FIRST_UID = 2001;

    private const int SIGTERM = 15;
    private const int SIGKILL = 9;
    private const int STOP_TIMEOUT = 20;

    private bool $stopping = false;
    private ?JobRunner $runner = null;

    public function __construct(
        private readonly string $listen,
        private readonly int $maxJobs,
        private readonly int $maxTimeout,
        private readonly int $workerUid = 1000,
        private readonly int $workerGid = 1000,
        private readonly string $jobsDir = Protocol::JOBS_DIR,
        private readonly int $firstUid = self::FIRST_UID,
    ) {
        if ($maxJobs < 1 || $maxJobs > self::POOL_SIZE) {
            throw new RemoteExecException(sprintf('The number of simultaneous jobs must be between 1 and %d', self::POOL_SIZE));
        }
        if ($maxTimeout < 1) {
            throw new RemoteExecException('Invalid maximum timeout');
        }
    }

    /**
     * @param array<string, string> $env
     */
    public static function fromEnvironment(array $env): self
    {
        return new self(
            $env['CONVERTER_LISTEN'] ?? sprintf('tcp://0.0.0.0:%d', Protocol::DEFAULT_PORT),
            self::intVariable($env, 'CONVERTER_MAX_JOBS', 4),
            self::intVariable($env, 'CONVERTER_MAX_TIMEOUT', Protocol::DEFAULT_TIMEOUT),
            self::intVariable($env, 'CONVERTER_WORKER_UID', 1000),
            self::intVariable($env, 'CONVERTER_WORKER_GID', 1000),
        );
    }

    public function run(): int
    {
        if (0 !== posix_geteuid()) {
            self::log('convert-server must run as root (with restricted capabilities)');

            return 1;
        }

        $socket = @stream_socket_server($this->listen, $errorCode, $errorMessage);
        if (false === $socket) {
            self::log(sprintf('Cannot listen on %s: %s', $this->listen, $errorMessage));

            return 1;
        }

        pcntl_async_signals(true);
        pcntl_signal(self::SIGTERM, $this->stop(...));
        pcntl_signal(2, $this->stop(...));

        self::log(sprintf('Listening on %s, %d jobs at most, %d seconds each at most', $this->listen, $this->maxJobs, $this->maxTimeout));

        /** @var array<int, int|null> $slots pid of the process handling each slot */
        $slots = array_fill(0, $this->maxJobs, null);

        while (!$this->stopping) {
            $this->reap($slots);

            $slot = array_search(null, $slots, true);
            if (false === $slot) {
                // Busy: the next connections wait in the backlog
                usleep(100_000);
                continue;
            }

            $read = [$socket];
            $write = $except = null;
            if (!@stream_select($read, $write, $except, 1)) {
                continue;
            }
            $connection = @stream_socket_accept($socket, 0);
            if (false === $connection) {
                continue;
            }

            $pid = pcntl_fork();
            if (-1 === $pid) {
                self::log('Cannot fork');
                fclose($connection);
                continue;
            }
            if (0 === $pid) {
                fclose($socket);
                exit($this->handle($connection, $this->firstUid + $slot));
            }

            fclose($connection);
            $slots[$slot] = $pid;
        }

        fclose($socket);
        $this->stopJobs($slots);

        return 0;
    }

    /**
     * @param resource $connection
     */
    private function handle($connection, int $uid): int
    {
        $this->runner = new JobRunner($this->jobsDir, $this->workerUid, $this->workerGid, $this->maxTimeout, self::log(...));
        try {
            $this->runner->handle($connection, $uid);
        } catch (\Throwable $e) {
            self::log(sprintf('Job of uid %d failed: %s', $uid, $e->getMessage()));

            return 1;
        } finally {
            @fclose($connection);
        }

        return 0;
    }

    private function stop(): void
    {
        $this->stopping = true;
        $this->runner?->terminate();
    }

    /**
     * @param array<int, int|null> $slots
     */
    private function reap(array &$slots): void
    {
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            $slot = array_search($pid, $slots, true);
            if (false !== $slot) {
                $slots[$slot] = null;
            }
        }
    }

    /**
     * @param array<int, int|null> $slots
     */
    private function stopJobs(array $slots): void
    {
        $pids = array_filter($slots);
        foreach ($pids as $pid) {
            posix_kill($pid, self::SIGTERM);
        }

        $deadline = time() + self::STOP_TIMEOUT;
        while ([] !== $pids && time() < $deadline) {
            foreach ($pids as $slot => $pid) {
                if (0 !== pcntl_waitpid($pid, $status, WNOHANG)) {
                    unset($pids[$slot]);
                }
            }
            usleep(100_000);
        }
        foreach ($pids as $pid) {
            posix_kill($pid, self::SIGKILL);
        }
    }

    /**
     * @param array<string, string> $env
     */
    private static function intVariable(array $env, string $name, int $default): int
    {
        $value = $env[$name] ?? '';
        if ('' === $value) {
            return $default;
        }
        if (!ctype_digit($value)) {
            throw new RemoteExecException(sprintf('%s must be a positive integer', $name));
        }

        return (int) $value;
    }

    private static function log(string $message): void
    {
        fwrite(STDERR, sprintf("[convert-server %s] %s\n", date('c'), $message));
    }
}
