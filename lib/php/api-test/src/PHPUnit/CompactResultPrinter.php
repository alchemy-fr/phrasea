<?php

declare(strict_types=1);

namespace Alchemy\ApiTest\PHPUnit;

use PHPUnit\Framework\TestResult;
use PHPUnit\TextUI\DefaultResultPrinter;

/**
 * Result printer designed for CI/agent consumption (e.g. `... | tail -n 50`):
 * no per-test progress output, full defect traces, then a one-line-per-defect
 * recap at the very end so the failing test names survive any log truncation.
 */
final class CompactResultPrinter extends DefaultResultPrinter
{
    protected function writeProgress(string $progress): void
    {
    }

    public function printResult(TestResult $result): void
    {
        parent::printResult($result);
        $this->printRecap($result);
    }

    private function printRecap(TestResult $result): void
    {
        $lines = [];
        $groups = [
            'ERROR' => $result->errors(),
            'FAIL' => $result->failures(),
            'WARN' => $result->warnings(),
        ];

        foreach ($groups as $label => $defects) {
            foreach ($defects as $defect) {
                $message = strtok(trim($defect->exceptionMessage()), "\n");
                if (false === $message) {
                    $message = '';
                }
                if (strlen($message) > 120) {
                    $message = substr($message, 0, 117).'...';
                }

                $lines[] = sprintf(
                    '  [%s] %s%s',
                    $label,
                    $defect->getTestName(),
                    '' !== $message ? ' - '.$message : ''
                );
            }
        }

        if (empty($lines)) {
            return;
        }

        $this->write(sprintf("\nDefects recap (%d):\n", count($lines)));

        foreach ($lines as $line) {
            $this->write($line."\n");
        }
    }
}
