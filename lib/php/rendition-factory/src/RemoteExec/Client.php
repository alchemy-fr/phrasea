<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * Runs a tool in the conversion container, as if it ran here: its output and its exit code are
 * relayed, the files it writes in the worker directory land where they were expected.
 *
 * stdin is not forwarded (no caller pipes data into these tools).
 */
final class Client
{
    public const int EXIT_FAILURE = 125;

    private const float CONNECT_TIMEOUT = 30.0;
    private const float POLL_INTERVAL = 1.0;

    private bool $aborted = false;

    public function __construct(
        private readonly string $url,
        private readonly string $workerDir = Protocol::WORKER_DIR,
        private readonly string $jobsDir = Protocol::JOBS_DIR,
        private readonly int $timeout = Protocol::DEFAULT_TIMEOUT,
    ) {
        if (!preg_match('#^(tcp|unix)://.+#', $url)) {
            throw new RemoteExecException(sprintf('Invalid converter URL "%s", expected tcp://host:port', $url));
        }
    }

    /**
     * Ends the running command (from a signal handler): the connection is closed, which makes
     * the conversion container kill the job.
     */
    public function abort(): void
    {
        $this->aborted = true;
    }

    /**
     * @param list<string> $args
     * @param resource     $stdout
     * @param resource     $stderr
     *
     * @return int the exit code of the tool
     *
     * @throws RemoteExecException
     */
    public function run(Tool $tool, array $args, $stdout, $stderr): int
    {
        $jobDir = $this->jobsDir.'/'.bin2hex(random_bytes(16));
        if (!@mkdir($jobDir, 0700)) {
            throw new RemoteExecException(sprintf('Cannot create the job directory in %s', $this->jobsDir));
        }

        try {
            $mapper = new PathMapper($this->workerDir, $jobDir, getcwd() ?: null);
            $request = new Request($tool, $mapper->map($tool, $args), $jobDir, $this->timeout);

            $exitCode = $this->execute($request, $stdout, $stderr);

            // The job directory is clean by now: only regular files and directories, owned by us
            $mapper->syncBack();

            return $exitCode;
        } finally {
            FileTree::remove($jobDir);
        }
    }

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    private function execute(Request $request, $stdout, $stderr): int
    {
        $connection = @stream_socket_client($this->url, $errorCode, $errorMessage, self::CONNECT_TIMEOUT);
        if (false === $connection) {
            throw new RemoteExecException(sprintf('Cannot reach the converter at %s: %s', $this->url, $errorMessage));
        }

        try {
            Protocol::write($connection, Protocol::REQUEST, $request->toJson());
            stream_set_blocking($connection, false);

            $buffer = '';
            while (true) {
                if ($this->aborted) {
                    throw new RemoteExecException('Aborted');
                }

                $read = [$connection];
                $write = $except = null;
                $ready = @stream_select($read, $write, $except, (int) self::POLL_INTERVAL);
                if (false === $ready) {
                    // Interrupted by a signal
                    continue;
                }
                if (0 === $ready) {
                    continue;
                }

                $data = fread($connection, 65536);
                if (false === $data || ('' === $data && feof($connection))) {
                    throw new RemoteExecException('The converter closed the connection before the end of the command');
                }
                $buffer .= $data;

                foreach (Protocol::decode($buffer) as [$type, $payload]) {
                    switch ($type) {
                        case Protocol::STDOUT:
                            fwrite($stdout, $payload);
                            break;
                        case Protocol::STDERR:
                            fwrite($stderr, $payload);
                            break;
                        case Protocol::EXIT:
                            return (int) $payload;
                        case Protocol::ERROR:
                            throw new RemoteExecException(sprintf('Converter: %s', $payload));
                        default:
                            throw new RemoteExecException('Unexpected answer from the converter');
                    }
                }
            }
        } finally {
            fclose($connection);
        }
    }
}
