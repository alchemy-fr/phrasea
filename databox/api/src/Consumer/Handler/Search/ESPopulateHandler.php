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
                $errorOutput = substr($errorOutput.$buffer, -self::ERROR_OUTPUT_LENGTH);
            }
        });

        if ($process->isSuccessful()) {
            return;
        }

        $error = sprintf('Populate process failed with exit code %s (%s)', $process->getExitCode() ?? 'none', $process->getExitCodeText() ?? 'unknown');

        // A killed process (OOM…) leaves its pass open and its index locked
        $this->populateLockManager->closeDeadPasses($message->index, $startedAt, $error);

        // Unrecoverable: a retry would start the whole populate again
        throw new UnrecoverableMessageHandlingException(sprintf("%s:\n%s", $error, trim($errorOutput)));
    }
}
