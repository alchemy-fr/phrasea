<?php

declare(strict_types=1);

namespace App\Tests\Unit\Configurator\Vendor\Database;

use App\Configurator\Vendor\Database\DatabaseRole;
use App\Configurator\Vendor\Database\DatabaseRoleManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class DatabaseRoleManagerTest extends TestCase
{
    private const string SUPERUSER = 'superadmin';

    /**
     * Statements executed on every connection, in order.
     *
     * @var string[]
     */
    private array $statements = [];

    public function testCreatesMissingRole(): void
    {
        $admin = $this->createConnection(fetchAssociative: false);

        (new DatabaseRoleManager())->configureRole($admin, new DatabaseRole('databox', "it's secret"));

        $this->assertSame([
            "CREATE ROLE \"databox\" WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD 'it''s secret'",
        ], $this->statements);
    }

    public function testUpdatesExistingRole(): void
    {
        $admin = $this->createConnection(fetchAssociative: ['rolsuper' => false]);

        (new DatabaseRoleManager())->configureRole($admin, new DatabaseRole('Data"box', 'pw'));

        $this->assertSame([
            "ALTER ROLE \"Data\"\"box\" WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD 'pw'",
        ], $this->statements);
    }

    public function testRefusesTheConnectedSuperuser(): void
    {
        $admin = $this->createConnection(fetchAssociative: false);

        try {
            (new DatabaseRoleManager())->configureRole($admin, new DatabaseRole(self::SUPERUSER, 'pw'));
            $this->fail('The superuser must be refused');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('is the superuser', $e->getMessage());
        }

        $this->assertSame([], $this->statements);
    }

    public function testRefusesAnotherSuperuser(): void
    {
        $admin = $this->createConnection(fetchAssociative: ['rolsuper' => true]);

        try {
            (new DatabaseRoleManager())->configureRole($admin, new DatabaseRole('postgres', 'pw'));
            $this->fail('A superuser must be refused');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('is a superuser', $e->getMessage());
        }

        $this->assertSame([], $this->statements);
    }

    public function testCreatesMissingDatabaseAndTransfersObjects(): void
    {
        $admin = $this->createConnection(fetchOne: ['FROM pg_database' => false]);
        $database = $this->createConnection(
            relations: [
                "'r', 'p'" => ['asset', 'Odd"Name'],
                "'S'" => ['free_seq'],
            ],
            routines: [['proname' => 'f_one', 'arguments' => 'a integer, b text']],
        );

        (new DatabaseRoleManager())->configureDatabase($admin, $database, 'databox', new DatabaseRole('databox', 'pw'));

        $this->assertSame([
            'CREATE DATABASE "databox" OWNER "databox"',
            'ALTER SCHEMA public OWNER TO "databox"',
            'ALTER TABLE public."asset" OWNER TO "databox"',
            'ALTER TABLE public."Odd""Name" OWNER TO "databox"',
            'ALTER SEQUENCE public."free_seq" OWNER TO "databox"',
            'ALTER ROUTINE public."f_one"(a integer, b text) OWNER TO "databox"',
            'REVOKE CONNECT ON DATABASE "databox" FROM PUBLIC',
            'GRANT CONNECT ON DATABASE "databox" TO "databox"',
        ], $this->statements);
    }

    public function testHandsOverExistingDatabase(): void
    {
        $admin = $this->createConnection(fetchOne: ['FROM pg_database' => 1]);
        $database = $this->createConnection();

        (new DatabaseRoleManager())->configureDatabase($admin, $database, 'keycloak2', new DatabaseRole('keycloak', 'pw'));

        $this->assertSame([
            'ALTER DATABASE "keycloak2" OWNER TO "keycloak"',
            'ALTER SCHEMA public OWNER TO "keycloak"',
            'REVOKE CONNECT ON DATABASE "keycloak2" FROM PUBLIC',
            'GRANT CONNECT ON DATABASE "keycloak2" TO "keycloak"',
        ], $this->statements);
    }

    public function testConfiguratorDatabase(): void
    {
        $admin = $this->createConnection(fetchOne: [
            'FROM pg_roles WHERE rolname' => false,
            'FROM pg_auth_members' => static fn (array $params): int|false => 'databox' === $params[1] ? 1 : false,
        ]);

        (new DatabaseRoleManager())->configureConfiguratorDatabase($admin, 'configurator', [
            new DatabaseRole('databox', 'pw'),
            new DatabaseRole('expose', 'pw'),
        ]);

        $this->assertSame([
            'CREATE ROLE "configurator_editor" WITH NOLOGIN',
            'REVOKE CONNECT ON DATABASE "configurator" FROM PUBLIC',
            'GRANT CONNECT ON DATABASE "configurator" TO "configurator_editor"',
            'REVOKE CREATE ON SCHEMA public FROM PUBLIC',
            'GRANT USAGE ON SCHEMA public TO "configurator_editor"',
            'GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO "configurator_editor"',
            'GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO "configurator_editor"',
            'ALTER DEFAULT PRIVILEGES FOR ROLE "superadmin" IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO "configurator_editor"',
            'ALTER DEFAULT PRIVILEGES FOR ROLE "superadmin" IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO "configurator_editor"',
            'GRANT "configurator_editor" TO "expose"',
        ], $this->statements);
    }

    /**
     * @param array<string, mixed>         $fetchOne  result by SQL fragment (a closure receives the parameters)
     * @param array<string, string[]>      $relations relation names by relkind list fragment
     * @param array<array<string, string>> $routines
     */
    private function createConnection(
        array $fetchOne = [],
        array|false $fetchAssociative = false,
        array $relations = [],
        array $routines = [],
    ): Connection {
        $connection = $this->createMock(Connection::class);
        $connection->method('quoteSingleIdentifier')
            ->willReturnCallback(static fn (string $identifier): string => '"'.str_replace('"', '""', $identifier).'"');
        $connection->method('quote')
            ->willReturnCallback(static fn (string $value): string => "'".str_replace("'", "''", $value)."'");
        $connection->method('executeStatement')
            ->willReturnCallback(function (string $sql): int {
                $this->statements[] = $sql;

                return 0;
            });
        $connection->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params = []) use ($fetchOne): mixed {
                if ('SELECT current_user' === $sql) {
                    return self::SUPERUSER;
                }
                foreach ($fetchOne as $fragment => $result) {
                    if (str_contains($sql, $fragment)) {
                        return $result instanceof \Closure ? $result($params) : $result;
                    }
                }

                throw new \LogicException('Unexpected query: '.$sql);
            });
        $connection->method('fetchAssociative')->willReturn($fetchAssociative);
        $connection->method('fetchFirstColumn')
            ->willReturnCallback(static function (string $sql) use ($relations): array {
                foreach ($relations as $fragment => $names) {
                    if (str_contains($sql, 'IN ('.$fragment.')')) {
                        return $names;
                    }
                }

                return [];
            });
        $connection->method('fetchAllAssociative')->willReturn($routines);

        return $connection;
    }
}
