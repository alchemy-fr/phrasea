<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Elasticsearch\PopulateLockManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Process\Process;

/**
 * Runs the populate in a child process: the worker gets its memory back at the end
 * of the run, instead of keeping what a populate of every asset made it grow to.
 */
#[AsMessageHandler]
final readonly class ESPopulateHandler
{
    private const int ERROR_OUTPUT_LENGTH = 1000;

    /**
     * What is kept of the error output to find the cause: after a fatal error, Symfony
     * writes the error again in a box, then the whole usage of the command.
     */
    private const int ERROR_OUTPUT_BUFFER_LENGTH = 8000;

    public function __construct(
        private PopulateLockManager $populateLockManager,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    public function __invoke(ESPopulate $message): void
    {
        $command = [
            PHP_BINARY,
            // A `-d memory_limit` given to the worker is not inherited, and the child may have to compile its own container
            '-d', 'memory_limit='.ini_get('memory_limit'),
            $this->projectDir.'/bin/console',
            'fos:elastica:populate',
            '--no-debug',
            '--no-interaction',
        ];
        if (null !== $message->index) {
            $command[] = '--index='.$message->index;
        }

        // No timeout: populating every asset can take hours
        $process = new Process($command, $this->projectDir, ['APP_ENV' => $this->environment], timeout: null);

        $errorOutput = '';
        $startedAt = new \DateTimeImmutable();
        $process->run(static function (string $type, string $buffer) use (&$errorOutput): void {
            if (Process::ERR === $type) {
                $errorOutput = substr($errorOutput.$buffer, -self::ERROR_OUTPUT_BUFFER_LENGTH);
            }
        });

        if ($process->isSuccessful()) {
            return;
        }

        $exitCode = $process->getExitCode() ?? 'none';
        $error = sprintf('Populate process failed with exit code %s (%s)', $exitCode, $process->getExitCodeText() ?? 'unknown');

        // A killed process (OOM…) leaves its pass open and its index locked
        $cause = self::extractCause($errorOutput);
        $this->populateLockManager->closeDeadPasses($message->index, $startedAt, null !== $cause ? sprintf('Exit code %s: %s', $exitCode, $cause) : $error);

        // Unrecoverable: a retry would start the whole populate again
        throw new UnrecoverableMessageHandlingException(sprintf("%s:\n%s", $error, trim(substr($errorOutput, -self::ERROR_OUTPUT_LENGTH))));
    }

    /**
     * The reason why the process died, from the end of its error output: the out-of-memory
     * error first (Symfony repeats it in a box, followed by the usage of the command), then
     * any other fatal error, then the last line written.
     */
    public static function extractCause(string $errorOutput): ?string
    {
        if (preg_match('/Allowed memory size of \d+ bytes exhausted[^\n]*/', $errorOutput, $matches)) {
            return trim($matches[0]);
        }

        $lines = array_reverse(array_values(array_filter(
            array_map(trim(...), preg_split('/\R/', $errorOutput) ?: []),
            static fn (string $line): bool => '' !== $line,
        )));
        foreach ($lines as $line) {
            if (str_contains($line, 'Fatal error:')) {
                return $line;
            }
        }

        return $lines[0] ?? null;
    }
}
