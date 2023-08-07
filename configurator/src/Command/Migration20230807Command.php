<?php

declare(strict_types=1);

namespace App\Command;

use App\Configurator\Vendor\Keycloak\KeycloakManager;
use App\Doctrine\DoctrineConnectionManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migration:20230807')]
final class Migration20230807Command extends Command
{
    public function __construct(
        private readonly KeycloakManager $keycloakManager,
        private readonly array $symfonyApplications,
        private readonly DoctrineConnectionManager $connections,
    )
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $apps = $this->symfonyApplications;
        $apps[] = 'auth';

        foreach ($apps as $app) {
            $output->writeln(sprintf('Migrating <info>%s</info>', $app));
            $connection = $this->connections->getConnection($app);

            $exists = $connection->fetchAssociative("SELECT EXISTS (
    SELECT FROM
        pg_tables
    WHERE
        schemaname = 'public' AND
        tablename  = 'oauth_client'
    );");

            if (!$exists['exists']) {
                continue;
            }

            $oauthClients = $connection->fetchAllAssociative('SELECT
id,
random_id,
secret,
redirect_uris,
allowed_grant_types
FROM oauth_client');

            foreach ($oauthClients as $row) {
                if ($row['id'] === $app.'-admin') {
                    continue;
                }
                if ('auth' === $app && in_array($row['id'], [
                    'databox-app',
                    'expose-app',
                    'uploader-app',
                    'databox-admin',
                    'expose-admin',
                    'uploader-admin',
                    ], true)) {
                    continue;
                }

                $redirectUris = unserialize($row['redirect_uris']);
                $allowedGrantTypes = unserialize($row['allowed_grant_types']);

                $this->keycloakManager->createClient(
                    $row['id'].'_'.$row['random_id'],
                    $row['secret'],
                    $redirectUris[0],
                    [
                        'standardFlowEnabled' => in_array('authorization_code', $allowedGrantTypes, true),
                        'serviceAccountsEnabled' => in_array('client_credentials', $allowedGrantTypes, true),
                        'directAccessGrantsEnabled' => in_array('password', $allowedGrantTypes, true),
                    ]
                );
            }
        }

        $connection = $this->connections->getConnection('auth');
        $users = $connection->fetchAllAssociative('SELECT
id,
username,
email_verified,
enabled,
roles,
password,
locale,
created_at
FROM user');

        foreach ($users as $user) {
            $this->keycloakManager->createUser();
        }


        return Command::SUCCESS;
    }
}
