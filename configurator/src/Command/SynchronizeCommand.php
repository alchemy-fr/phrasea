<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Configurator\Vendor\Keycloak\KeycloakConfigurator;

#[AsCommand(name: 'synchronize', description: 'Synchronize all parameters from env vars')]
final class SynchronizeCommand extends Command
{
    public function __construct(
        private readonly KeycloakConfigurator $keycloakConfigurator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->keycloakConfigurator->synchronize();
        
        return Command::SUCCESS;
    }
}
