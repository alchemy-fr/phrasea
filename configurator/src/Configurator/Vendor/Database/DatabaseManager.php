<?php

namespace App\Configurator\Vendor\Database;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class DatabaseManager
{
    public function __construct(
        private KernelInterface $kernel,
    ) {
    }

    public function createDatabase(string $connectionName): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'doctrine:database:create',
            '--connection' => $connectionName,
            '--if-not-exists' => true,
        ]);

        $output = new BufferedOutput();
        $result = $application->run($input, $output);

        if (Command::SUCCESS !== $result) {
            throw new \RuntimeException(sprintf('Failed to create database for connection "%s": %s', $connectionName, $output->fetch()));
        }
    }
}
