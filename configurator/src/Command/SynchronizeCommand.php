<?php

declare(strict_types=1);

namespace App\Command;

use App\Configurator\Vendor\Keycloak\KeycloakConfigurator;
use App\Util\EnvHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'synchronize', description: 'Synchronize all parameters from env vars')]
final class SynchronizeCommand extends Command
{
    public function __construct(
        private readonly KeycloakConfigurator $keycloakConfigurator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!EnvHelper::getBooleanEnv('CONFIGURATOR_CONFIGURE_KEYCLOAK')) {
            $output->writeln('Skipping Keycloak synchronization (disabled by environment variable)...');

            return Command::SUCCESS;
        }

        $output->writeln('Synchronizing Keycloak...');
        $this->keycloakConfigurator->synchronize();

        return Command::SUCCESS;
    }
}
