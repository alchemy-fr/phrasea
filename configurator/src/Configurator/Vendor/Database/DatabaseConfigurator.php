<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Database;

use App\Configurator\ConfiguratorInterface;
use App\Util\EnvHelper;
use App\Util\HttpClientUtil;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class DatabaseConfigurator implements ConfiguratorInterface
{
    public function __construct(
        private DatabaseManager $databaseManager,
    ) {
    }

    public static function getPriority(): int
    {
        return 0;
    }

    public static function getName(): string
    {
        return 'database';
    }

    public function configure(OutputInterface $output, array $presets): void
    {
        HttpClientUtil::waitForHostPort($output, EnvHelper::getEnvOrThrow('POSTGRES_HOST'), (int) EnvHelper::getEnvOrThrow('POSTGRES_PORT'));

        $databases = [
            'report',
            'keycloak',
        ];

        if (in_array('dev', $presets, true)) {
            $databases[] = 'keycloak2';
        }

        foreach ($databases as $connectionName) {
            $output->writeln(sprintf('Creating database for connection "%s"...', $connectionName));
            $this->databaseManager->createDatabase($connectionName);
        }
    }
}
