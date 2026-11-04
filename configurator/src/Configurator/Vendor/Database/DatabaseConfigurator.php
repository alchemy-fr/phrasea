<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Database;

use App\Configurator\ConfiguratorInterface;
use App\Configurator\ConfigureContext;
use App\Util\EnvHelper;
use App\Util\HttpClientUtil;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 0)]
final readonly class DatabaseConfigurator implements ConfiguratorInterface
{
    private const string CONFIGURATOR_CONNECTION = 'configurator';

    public function __construct(
        private DatabaseManager $databaseManager,
        private DatabaseRoleManager $roleManager,
        private ManagerRegistry $doctrine,
    ) {
    }

    public static function getName(): string
    {
        return 'database';
    }

    public function configure(OutputInterface $output, ConfigureContext $context): void
    {
        HttpClientUtil::waitForHostPort($output, EnvHelper::getEnvOrThrow('POSTGRES_HOST'), (int) EnvHelper::getEnvOrThrow('POSTGRES_PORT'));

        $databases = $this->getDatabases($context);
        $roles = $this->getRoles($databases);

        $admin = $this->getConnection(self::CONFIGURATOR_CONNECTION);
        $apiRoles = [];
        foreach ($databases as $connectionName => $database) {
            $role = $roles[$database['rolePrefix']];
            if (null === $role) {
                // Without a role of its own, an API creates its database in its bin/setup.sh.
                if (!$database['api']) {
                    $output->writeln(sprintf('Creating database for connection "%s"...', $connectionName));
                    $this->databaseManager->createDatabase($connectionName);
                }

                continue;
            }

            $databaseName = EnvHelper::getEnvOrThrow(strtoupper($connectionName).'_DB_NAME');
            $output->writeln(sprintf('Configuring database "%s" for role "%s"...', $databaseName, $role->name));
            $this->roleManager->configureRole($admin, $role);
            $this->roleManager->configureDatabase($admin, $this->getConnection($connectionName), $databaseName, $role);

            if ($database['api']) {
                $apiRoles[$role->name] = $role;
            }
        }

        if (!empty($apiRoles)) {
            $databaseName = EnvHelper::getEnvOrThrow('CONFIGURATOR_DB_NAME');
            $output->writeln(sprintf('Granting the API roles access to the configurator database "%s"...', $databaseName));
            $this->roleManager->configureConfiguratorDatabase($admin, $databaseName, array_values($apiRoles));
        }
    }

    /**
     * Doctrine connection name => role variables prefix ({PREFIX}_POSTGRES_USER/_PASSWORD), and
     * whether it is the database of an API. The name of the database is {CONNECTION}_DB_NAME.
     *
     * @return array<string, array{rolePrefix: string, api: bool}>
     */
    private function getDatabases(ConfigureContext $context): array
    {
        $databases = [
            'databox' => ['rolePrefix' => 'DATABOX', 'api' => true],
            'expose' => ['rolePrefix' => 'EXPOSE', 'api' => true],
            'uploader' => ['rolePrefix' => 'UPLOADER', 'api' => true],
            'report' => ['rolePrefix' => 'REPORT', 'api' => false],
            'keycloak' => ['rolePrefix' => 'KEYCLOAK', 'api' => false],
        ];

        if ($context->hasPreset('dev')) {
            $databases['keycloak2'] = ['rolePrefix' => 'KEYCLOAK', 'api' => false];
        }

        return $databases;
    }

    /**
     * Checked before any change, so that a misconfiguration leaves the databases untouched.
     *
     * @param array<string, array{rolePrefix: string, api: bool}> $databases
     *
     * @return array<string, DatabaseRole|null> indexed by role variables prefix
     */
    private function getRoles(array $databases): array
    {
        $roles = [];
        $passwords = [];
        foreach ($databases as $database) {
            $prefix = $database['rolePrefix'];
            if (array_key_exists($prefix, $roles)) {
                continue;
            }

            $role = $roles[$prefix] = DatabaseRole::fromEnv($prefix);
            if (null === $role) {
                continue;
            }

            if (isset($passwords[$role->name]) && $passwords[$role->name] !== $role->password) {
                throw new \InvalidArgumentException(sprintf('The role "%s" is given different passwords by the *_POSTGRES_PASSWORD variables.', $role->name));
            }
            $passwords[$role->name] = $role->password;
        }

        return $roles;
    }

    private function getConnection(string $name): Connection
    {
        $connection = $this->doctrine->getConnection($name);
        if (!$connection instanceof Connection) {
            throw new \LogicException(sprintf('Connection "%s" is not a DBAL connection.', $name));
        }

        return $connection;
    }
}
