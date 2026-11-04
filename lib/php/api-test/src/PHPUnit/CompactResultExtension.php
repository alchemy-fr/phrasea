<?php

declare(strict_types=1);

namespace Alchemy\ApiTest\PHPUnit;

use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use PHPUnit\Event\Test\PhpunitWarningTriggered;
use PHPUnit\Event\Test\PhpunitWarningTriggeredSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Output designed for CI/agent consumption (e.g. `... | tail -n 50`): no
 * per-test progress output, full defect traces (PHPUnit default result
 * output), then a one-line-per-defect recap at the very end so the failing
 * test names survive any log truncation.
 *
 * Enable it with `--extension 'Alchemy\ApiTest\PHPUnit\CompactResultExtension'`.
 */
final class CompactResultExtension implements Extension
{
    /**
     * @var list<string>
     */
    private array $lines = [];

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if ($configuration->noOutput()) {
            return;
        }

        $facade->replaceProgressOutput();

        $extension = $this;
        $facade->registerSubscribers(
            new class($extension) implements ErroredSubscriber {
                public function __construct(private readonly CompactResultExtension $extension)
                {
                }

                public function notify(Errored $event): void
                {
                    $this->extension->add('ERROR', $event->test()->id(), $event->throwable()->message());
                }
            },
            new class($extension) implements FailedSubscriber {
                public function __construct(private readonly CompactResultExtension $extension)
                {
                }

                public function notify(Failed $event): void
                {
                    $this->extension->add('FAIL', $event->test()->id(), $event->throwable()->message());
                }
            },
            new class($extension) implements PhpunitWarningTriggeredSubscriber {
                public function __construct(private readonly CompactResultExtension $extension)
                {
                }

                public function notify(PhpunitWarningTriggered $event): void
                {
                    $this->extension->add('WARN', $event->test()->id(), $event->message());
                }
            },
        );

        // PHPUnit prints its result (defect details and counts) once the run is
        // over, after the last event: the recap is printed when the process exits.
        register_shutdown_function($this->printRecap(...));
    }

    /**
     * @internal
     */
    public function add(string $label, string $testName, string $message): void
    {
        $message = strtok(trim($message), "\n");
        if (false === $message) {
            $message = '';
        }
        if (strlen($message) > 120) {
            $message = substr($message, 0, 117).'...';
        }

        $this->lines[] = sprintf('  [%s] %s%s', $label, $testName, '' !== $message ? ' - '.$message : '');
    }

    private function printRecap(): void
    {
        if (empty($this->lines)) {
            return;
        }

        echo sprintf("\nDefects recap (%d):\n", count($this->lines));
        foreach ($this->lines as $line) {
            echo $line."\n";
        }
    }
}
