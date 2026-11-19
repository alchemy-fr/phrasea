<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Database;

use Doctrine\DBAL\Connection;

/**
 * Gives each application a role of its own, owner of its database only, and lets the API
 * roles use the shared configurator database. Every step is idempotent: it runs at each setup.
 *
 * The connections given here must be opened with the superuser.
 */
final readonly class DatabaseRoleManager
{
    /**
     * Group role of the APIs in the configurator database: they read the global configuration
     * and edit it from their admin ("Global Config", Databox client theme).
     */
    public const string CONFIGURATOR_GROUP = 'configurator_editor';

    private const string ROLE_ATTRIBUTES = 'LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS';

    /**
     * Creates the role, or resets its attributes and password.
     */
    public function configureRole(Connection $admin, DatabaseRole $role): void
    {
        if ($role->name === $admin->fetchOne('SELECT current_user')) {
            throw new \InvalidArgumentException(sprintf('The role "%s" is the superuser the configurator connects with: give the application a role of its own.', $role->name));
        }

        $existing = $admin->fetchAssociative('SELECT rolsuper FROM pg_roles WHERE rolname = ?', [$role->name]);
        if (false !== $existing && $existing['rolsuper']) {
            throw new \InvalidArgumentException(sprintf('The role "%s" is a superuser: give the application a role of its own.', $role->name));
        }

        $admin->executeStatement(sprintf(
            '%s ROLE %s WITH %s PASSWORD %s',
            false === $existing ? 'CREATE' : 'ALTER',
            $admin->quoteSingleIdentifier($role->name),
            self::ROLE_ATTRIBUTES,
            $admin->quote($role->password),
        ));
    }

    /**
     * Makes the role the owner of the database and of everything the application created in it,
     * and the only role (beside the superusers) allowed to connect.
     *
     * @param Connection $database a connection to $databaseName, opened only once the database exists
     */
    public function configureDatabase(Connection $admin, Connection $database, string $databaseName, DatabaseRole $role): void
    {
        $quotedDatabase = $admin->quoteSingleIdentifier($databaseName);
        $quotedRole = $admin->quoteSingleIdentifier($role->name);

        if (false === $admin->fetchOne('SELECT 1 FROM pg_database WHERE datname = ?', [$databaseName])) {
            $admin->executeStatement(sprintf('CREATE DATABASE %s OWNER %s', $quotedDatabase, $quotedRole));
        } else {
            $admin->executeStatement(sprintf('ALTER DATABASE %s OWNER TO %s', $quotedDatabase, $quotedRole));
        }

        $this->transferPublicSchema($database, $role);

        $admin->executeStatement(sprintf('REVOKE CONNECT ON DATABASE %s FROM PUBLIC', $quotedDatabase));
        $admin->executeStatement(sprintf('GRANT CONNECT ON DATABASE %s TO %s', $quotedDatabase, $quotedRole));
    }

    /**
     * Lets the API roles read and write the rows of the configurator database (no DDL), and
     * keeps every other role out of it. The configurator migrations run with the superuser,
     * whose future tables get the same grants.
     *
     * @param Connection     $admin a connection to the configurator database
     * @param DatabaseRole[] $roles
     */
    public function configureConfiguratorDatabase(Connection $admin, string $databaseName, array $roles): void
    {
        $quotedDatabase = $admin->quoteSingleIdentifier($databaseName);
        $quotedGroup = $admin->quoteSingleIdentifier(self::CONFIGURATOR_GROUP);
        $quotedSuperuser = $admin->quoteSingleIdentifier((string) $admin->fetchOne('SELECT current_user'));

        if (false === $admin->fetchOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [self::CONFIGURATOR_GROUP])) {
            $admin->executeStatement(sprintf('CREATE ROLE %s WITH NOLOGIN', $quotedGroup));
        }

        foreach ([
            sprintf('REVOKE CONNECT ON DATABASE %s FROM PUBLIC', $quotedDatabase),
            sprintf('GRANT CONNECT ON DATABASE %s TO %s', $quotedDatabase, $quotedGroup),
            'REVOKE CREATE ON SCHEMA public FROM PUBLIC',
            sprintf('GRANT USAGE ON SCHEMA public TO %s', $quotedGroup),
            sprintf('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO %s', $quotedGroup),
            sprintf('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO %s', $quotedGroup),
            sprintf('ALTER DEFAULT PRIVILEGES FOR ROLE %s IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %s', $quotedSuperuser, $quotedGroup),
            sprintf('ALTER DEFAULT PRIVILEGES FOR ROLE %s IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO %s', $quotedSuperuser, $quotedGroup),
        ] as $sql) {
            $admin->executeStatement($sql);
        }

        foreach ($roles as $role) {
            $isMember = $admin->fetchOne(
                'SELECT 1 FROM pg_auth_members m
                JOIN pg_roles g ON g.oid = m.roleid
                JOIN pg_roles r ON r.oid = m.member
                WHERE g.rolname = ? AND r.rolname = ?',
                [self::CONFIGURATOR_GROUP, $role->name],
            );
            if (false === $isMember) {
                $admin->executeStatement(sprintf('GRANT %s TO %s', $quotedGroup, $admin->quoteSingleIdentifier($role->name)));
            }
        }
    }

    /**
     * REASSIGN OWNED BY the superuser is not an option: Postgres refuses it for the objects of
     * the bootstrap superuser, which owns the system catalogs. Objects belonging to an extension
     * (ltree…) stay with the superuser.
     */
    private function transferPublicSchema(Connection $database, DatabaseRole $role): void
    {
        $quotedRole = $database->quoteSingleIdentifier($role->name);
        $database->executeStatement(sprintf('ALTER SCHEMA public OWNER TO %s', $quotedRole));

        // Tables first: their sequences (serial, identity) follow them, and refuse a change of
        // their own.
        foreach ([
            'TABLE' => ['r', 'p'],
            'VIEW' => ['v'],
            'MATERIALIZED VIEW' => ['m'],
            'SEQUENCE' => ['S'],
        ] as $objectType => $relationKinds) {
            $relations = $database->fetchFirstColumn(
                sprintf(
                    'SELECT c.relname FROM pg_class c
                    JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = \'public\'
                    AND c.relkind IN (%s)
                    AND c.relowner <> (SELECT oid FROM pg_roles WHERE rolname = ?)
                    AND NOT EXISTS (
                        SELECT 1 FROM pg_depend d
                        WHERE d.classid = \'pg_class\'::regclass AND d.objid = c.oid AND d.deptype = \'e\'
                    )
                    ORDER BY c.relname',
                    implode(', ', array_map($database->quote(...), $relationKinds)),
                ),
                [$role->name],
            );

            foreach ($relations as $relation) {
                $database->executeStatement(sprintf(
                    'ALTER %s public.%s OWNER TO %s',
                    $objectType,
                    $database->quoteSingleIdentifier($relation),
                    $quotedRole,
                ));
            }
        }

        $routines = $database->fetchAllAssociative(
            'SELECT p.proname, pg_get_function_identity_arguments(p.oid) AS arguments FROM pg_proc p
            JOIN pg_namespace n ON n.oid = p.pronamespace
            WHERE n.nspname = \'public\'
            AND p.prokind IN (\'f\', \'p\')
            AND p.proowner <> (SELECT oid FROM pg_roles WHERE rolname = ?)
            AND NOT EXISTS (
                SELECT 1 FROM pg_depend d
                WHERE d.classid = \'pg_proc\'::regclass AND d.objid = p.oid AND d.deptype = \'e\'
            )
            ORDER BY p.proname',
            [$role->name],
        );

        foreach ($routines as $routine) {
            $database->executeStatement(sprintf(
                'ALTER ROUTINE public.%s(%s) OWNER TO %s',
                $database->quoteSingleIdentifier($routine['proname']),
                $routine['arguments'],
                $quotedRole,
            ));
        }
    }
}
