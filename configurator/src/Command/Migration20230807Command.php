<?php

declare(strict_types=1);

namespace App\Command;

use App\Configurator\Vendor\Keycloak\KeycloakInterface;
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
                    $redirectUris[0] ?? null,
                    [
                        'standardFlowEnabled' => in_array('authorization_code', $allowedGrantTypes, true),
                        'serviceAccountsEnabled' => in_array('client_credentials', $allowedGrantTypes, true),
                        'directAccessGrantsEnabled' => in_array('password', $allowedGrantTypes, true),
                    ]
                );
            }
        }

        $output->writeln('Migrating Users');
        $connection = $this->connections->getConnection('auth');
        $groups = $connection->fetchAllAssociative('SELECT
id,
name,
created_at
FROM "group"');

        $groupMap = [];
        foreach ($groups as $row) {
            $group = $this->keycloakManager->createGroup([
                'name' => $row['name'],
                'attributes' => [
                    'ps-auth-legacy-id' => [$row['id']],
                ],
            ]);
            $groupMap[$row['id']] = $group['id'];
        }

        $users = $connection->fetchAllAssociative('SELECT
id,
username,
email_verified,
enabled,
roles,
password,
locale,
created_at
FROM "user"');

        $userMap = [];
        foreach ($users as $row) {
            $roles = json_decode($row['roles'], true, 512, JSON_THROW_ON_ERROR);
            $realmRoles = [];
            foreach ($roles as $role) {
                $realmRoles = array_merge($realmRoles, match ($role) {
                    'ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_CHUCK-NORRIS' => [
                        KeycloakInterface::GROUP_ADMIN,
                        KeycloakInterface::GROUP_SUPER_ADMIN,
                    ],
                    'ROLE_TECH' => [KeycloakInterface::GROUP_TECH],
                    'ROLE_ADMIN_OAUTH_CLIENTS',
                    'ROLE_ADMIN_USERS' => [KeycloakInterface::GROUP_USER_ADMIN, KeycloakInterface::GROUP_GROUP_ADMIN],
                    default => [],
                });
            }
            $realmRoles = array_unique($realmRoles);

            $user = $this->keycloakManager->createUser([
                'createdTimestamp' => (new \DateTimeImmutable($row['created_at']))->getTimestamp(),
                'username' => $row['username'],
                'email' => $row['username'],
                'emailVerified' => $row['email_verified'],
                'enabled' => $row['enabled'],
                'attributes' => [
                    'ps-auth-legacy-id' => $row['id'],
                ],
                'requiredActions' => [
                    'UPDATE_PASSWORD',
                ]
            ]);
            $userMap[$row['id']] = $user['id'];

            $userGroups = $connection->fetchAllAssociative('SELECT
group_id
FROM "user_group" WHERE user_id = :uid', [
                'uid' => $row['id'],
            ]);

            foreach ($userGroups as $userGroupRow) {
                $this->keycloakManager->addUserToGroup($user['id'], $groupMap[$userGroupRow['group_id']]);
            }

            $this->keycloakManager->addRolesToUser($user['id'], $realmRoles);
        }

        $this->replaceInDb([
            'databox' => [
                'asset_data_template' => [
                    'owner_id',
                ],
                'asset' => [
                    'owner_id',
                ],
                'collection' => [
                    'owner_id',
                ],
                'tag_filter_rule' => [
                    'user_id',
                ],
                'rendition_rule' => [
                    'user_id',
                ],
                'user_preference' => [
                    'user_id',
                ],
                'workspace' => [
                    'owner_id',
                ],
            ],
            'expose' => [
                'asset' => [
                    'owner_id',
                ],
                'publication_profile' => [
                    'owner_id',
                ],
                'publication' => [
                    'owner_id',
                ],
            ],
            'notify' => [
                'contact' => [
                    'user_id',
                ],
            ],
            'uploader' => [
                'asset' => [
                    'user_id',
                ],
                'asset_commit' => [
                    'user_id',
                ],
            ],
        ], $userMap);

        $this->replaceInDb([
            'databox' => [
                'access_control_entry' => [
                    'user_id',
                ]
            ]
        ], $groupMap);


        return Command::SUCCESS;
    }

    private function replaceInDb(array $tableMap, array $valueMap): void
    {
        foreach ($tableMap as $connectionName => $tables) {
            $connection = $this->connections->getConnection($connectionName);
            foreach ($tables as $tbl => $columns) {
                foreach ($columns as $col) {
                    foreach ($valueMap as $old => $new) {
                        $connection->executeQuery(sprintf('UPDATE "%1$s" SET %2$s = :new WHERE %2$s = :old', $tbl, $col), [
                            'old' => $old,
                            'new' => $new,
                        ]);
                    }
                }
            }
        }
    }
}
