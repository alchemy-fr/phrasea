<?php

declare(strict_types=1);

namespace App\Command;

use App\Configurator\Vendor\Keycloak\KeycloakConfigurator;
use App\Configurator\Vendor\RabbitMq\RabbitMqConfigurator;
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
        private readonly RabbitMqConfigurator $rabbitMqConfigurator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (EnvHelper::getBooleanEnv('CONFIGURATOR_CONFIGURE_KEYCLOAK')) {
            $output->writeln('Synchronizing Keycloak...');
            $this->keycloakConfigurator->synchronize();
        } else {
            $output->writeln('Skipping Keycloak synchronization (disabled by environment variable)...');
        }

        if (EnvHelper::getBooleanEnv('CONFIGURATOR_CONFIGURE_RABBITMQ')) {
            $output->writeln('Synchronizing RabbitMQ...');
            $this->rabbitMqConfigurator->synchronize($output);
        } else {
            $output->writeln('Skipping RabbitMQ synchronization (disabled by environment variable)...');
        }

        return Command::SUCCESS;
    }
}
