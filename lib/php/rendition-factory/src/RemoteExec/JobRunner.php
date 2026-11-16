<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * Runs the command of one connection, in the conversion container, as the uid of its job slot:
 *
 * 1. the request is checked (tool of the whitelist, job directory of the worker, timeout);
 * 2. the job directory is given to the uid of the slot (0700), which then sees neither the other
 *    jobs nor the worker directory;
 * 3. the tool runs through setpriv (that uid, no group, no new privileges), in its own process
 *    group, with a minimal environment; its output is relayed as it comes; the group is killed
 *    on timeout or when the worker hangs up;
 * 4. whatever happened: every process of the uid is killed, then everything that is neither a
 *    regular file nor a directory is removed from the job directory, which goes back to the
 *    worker. Only then is the exit code sent: the worker never follows a link planted by the
 *    tool (out.jpg -> /proc/self/environ would hand it its own secrets).
 */
final class JobRunner
{
    private const string SETSID = '/usr/bin/setsid';
    private const string SETPRIV = '/bin/setpriv';
    private const string SHELL = '/bin/sh';
    private const string PATH = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';

    private const int SIGKILL = 9;
    private const float REQUEST_TIMEOUT = 30.0;
    private const int WRITE_TIMEOUT = 60;
    private const int CHUNK_SIZE = 65536;

    private bool $terminating = false;

    /**
     * @param \Closure(string): void $log
     */
    public function __construct(
        private readonly string $jobsDir,
        private readonly int $workerUid,
        private readonly int $workerGid,
        private readonly int $maxTimeout,
        private readonly \Closure $log,
    ) {
    }

    /**
     * The server is stopping: the job is killed as if the worker had hung up.
     */
    public function terminate(): void
    {
        $this->terminating = true;
    }

    /**
     * @param resource $connection
     */
    public function handle($connection, int $uid): void
    {
        stream_set_timeout($connection, self::WRITE_TIMEOUT);

        $json = $this->readRequest($connection);
        if (null === $json) {
            return;
        }

        try {
            $request = Request::fromJson($json, $this->maxTimeout);
            $job = JobDirectory::open($request->cwd, $this->jobsDir, $this->workerUid);
        } catch (RemoteExecException $e) {
            ($this->log)(sprintf('Refused: %s', $e->getMessage()));
            $this->send($connection, Protocol::ERROR, $e->getMessage());

            return;
        }

        $start = microtime(true);
        $exitCode = null;
        $error = null;
        $workerGone = false;
        try {
            $this->killAll($uid);
            $job->chown($uid, $uid);
            $exitCode = $this->run($request, $job, $uid, $connection);
        } catch (WorkerGoneException) {
            $workerGone = true;
        } catch (RemoteExecException $e) {
            $error = $e->getMessage();
        } finally {
            $this->killAll($uid);
            $job->removeSpecialFiles();
            $job->chown($this->workerUid, $this->workerGid);
        }

        ($this->log)(sprintf('%s: %s in %.1fs', $request->tool->value, $workerGone ? 'aborted' : ($error ?? 'exit '.$exitCode), microtime(true) - $start));

        if ($workerGone) {
            // Nobody will remove it
            $job->remove();

            return;
        }
        if (null !== $error) {
            $this->send($connection, Protocol::ERROR, $error);

            return;
        }
        $this->send($connection, Protocol::EXIT, (string) $exitCode);
    }

    /**
     * @param resource $connection
     */
    private function readRequest($connection): ?string
    {
        $deadline = microtime(true) + self::REQUEST_TIMEOUT;
        $buffer = '';
        while (!$this->terminating && microtime(true) < $deadline) {
            $read = [$connection];
            $write = $except = null;
            if (!@stream_select($read, $write, $except, 1)) {
                continue;
            }
            $data = fread($connection, self::CHUNK_SIZE);
            if (false === $data || ('' === $data && feof($connection))) {
                return null;
            }
            $buffer .= $data;

            try {
                $frames = Protocol::decode($buffer);
            } catch (RemoteExecException) {
                return null;
            }
            if ([] !== $frames) {
                [$type, $payload] = $frames[0];

                return Protocol::REQUEST === $type ? $payload : null;
            }
        }

        return null;
    }

    /**
     * @param resource $connection
     *
     * @throws WorkerGoneException
     * @throws RemoteExecException
     */
    private function run(Request $request, JobDirectory $job, int $uid, $connection): int
    {
        $command = [
            self::SETSID,
            self::SHELL, '-c', self::closeDescriptorsScript(), 'sh',
            self::SETPRIV, '--reuid='.$uid, '--regid='.$uid, '--clear-groups', '--no-new-privs', '--',
            $request->tool->getBinary(),
            ...$request->args,
        ];
        $environment = [
            'PATH' => self::PATH,
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
            'HOME' => $job->path,
            'TMPDIR' => $job->path,
        ] + $request->tool->getEnvironment();

        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $job->path, $environment);
        if (!is_resource($process)) {
            throw new RemoteExecException('Cannot start the tool');
        }
        // setsid makes the tool the leader of a new process group, whose id is its pid
        $groupId = proc_get_status($process)['pid'];

        try {
            return $this->relay($process, $pipes, $connection, $request);
        } finally {
            @posix_kill(-$groupId, self::SIGKILL);
            foreach ($pipes as $pipe) {
                @fclose($pipe);
            }
            proc_close($process);
        }
    }

    /**
     * @param resource             $process
     * @param array<int, resource> $pipes
     * @param resource             $connection
     */
    private function relay($process, array $pipes, $connection, Request $request): int
    {
        $deadline = microtime(true) + $request->timeout;
        $types = [1 => Protocol::STDOUT, 2 => Protocol::STDERR];
        $open = [];
        foreach ($types as $fd => $type) {
            stream_set_blocking($pipes[$fd], false);
            $open[$fd] = $pipes[$fd];
        }

        while (true) {
            if ($this->terminating) {
                throw new WorkerGoneException();
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                // Whatever is left in the pipes (a detached child may keep them open: no waiting for EOF)
                foreach ($open as $fd => $pipe) {
                    for ($i = 0; $i < 64 && '' !== ($data = (string) fread($pipe, self::CHUNK_SIZE)); ++$i) {
                        $this->send($connection, $types[$fd], $data);
                    }
                }

                return $status['signaled'] ? 128 + $status['termsig'] : $status['exitcode'];
            }

            if (microtime(true) >= $deadline) {
                throw new RemoteExecException(sprintf('%s killed after %d seconds', $request->tool->value, $request->timeout));
            }

            $read = array_values($open);
            $read[] = $connection;
            $write = $except = null;
            if (!@stream_select($read, $write, $except, 0, 200000)) {
                continue;
            }

            foreach ($read as $stream) {
                if ($stream === $connection) {
                    // The worker only waits: anything readable means it hung up (or misbehaves)
                    throw new WorkerGoneException();
                }
                $fd = array_search($stream, $open, true);
                $data = fread($stream, self::CHUNK_SIZE);
                if (false === $data || ('' === $data && feof($stream))) {
                    unset($open[$fd]);
                    continue;
                }
                if ('' !== $data) {
                    $this->send($connection, $types[$fd], $data);
                }
            }
        }
    }

    /**
     * PHP marks none of its sockets close-on-exec: without this, the tool would inherit the
     * connection of the worker (and could answer in the name of the server). Every descriptor
     * open above stderr is closed when the shell execs the tool; those proc_open() creates are
     * already handled by PHP.
     */
    private static function closeDescriptorsScript(): string
    {
        $script = 'exec "$@"';
        foreach (@scandir('/proc/self/fd') ?: [] as $fd) {
            if (ctype_digit($fd) && (int) $fd > 2) {
                $script .= ' '.$fd.'>&-';
            }
        }

        return $script;
    }

    /**
     * Kills every process of the uid, until none is left: none of them outlives its job, nor
     * reads the files of the next one. kill(-1) from a process of that uid reaches all of them at
     * once, the ones forked meanwhile included.
     */
    private function killAll(int $uid): void
    {
        for ($attempt = 1;; ++$attempt) {
            $pid = pcntl_fork();
            if (-1 === $pid) {
                throw new RemoteExecException('Cannot fork');
            }
            if (0 === $pid) {
                if (posix_setgid($uid) && posix_setuid($uid) && posix_getuid() === $uid && posix_geteuid() === $uid) {
                    posix_kill(-1, self::SIGKILL);
                }
                posix_kill(posix_getpid(), self::SIGKILL);
            }
            pcntl_waitpid($pid, $status);

            if (!self::hasProcesses($uid)) {
                return;
            }
            usleep(min(100_000, 5_000 * $attempt));
        }
    }

    private static function hasProcesses(int $uid): bool
    {
        foreach (glob('/proc/[0-9]*/status', GLOB_NOSORT) ?: [] as $statusFile) {
            $status = @file_get_contents($statusFile);
            if (false === $status) {
                continue;
            }
            // A zombie holds nothing but its pid, until init reaps it
            if (preg_match('/^State:\s+[ZX]/m', $status)) {
                continue;
            }
            if (preg_match('/^Uid:\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/m', $status, $matches)
                && in_array((string) $uid, array_slice($matches, 1), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param resource $connection
     *
     * @throws WorkerGoneException
     */
    private function send($connection, string $type, string $payload): void
    {
        try {
            Protocol::writeChunked($connection, $type, $payload);
        } catch (RemoteExecException) {
            throw new WorkerGoneException();
        }
    }
}
