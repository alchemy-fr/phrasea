<?php

declare(strict_types=1);

namespace App\Tests\Unit\Configurator\Vendor\Database;

use App\Configurator\Vendor\Database\DatabaseRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseRoleTest extends TestCase
{
    private const string PREFIX = 'PHPUNIT_APP';

    protected function tearDown(): void
    {
        putenv(self::PREFIX.'_POSTGRES_USER');
        putenv(self::PREFIX.'_POSTGRES_PASSWORD');
    }

    public function testNoRoleWhileBothVariablesAreEmpty(): void
    {
        $this->assertNull(DatabaseRole::fromEnv(self::PREFIX));

        putenv(self::PREFIX.'_POSTGRES_USER=');
        putenv(self::PREFIX.'_POSTGRES_PASSWORD=');
        $this->assertNull(DatabaseRole::fromEnv(self::PREFIX));
    }

    public function testRoleFromEnv(): void
    {
        putenv(self::PREFIX.'_POSTGRES_USER=databox');
        putenv(self::PREFIX.'_POSTGRES_PASSWORD=s3cr3t');

        $role = DatabaseRole::fromEnv(self::PREFIX);

        $this->assertNotNull($role);
        $this->assertSame('databox', $role->name);
        $this->assertSame('s3cr3t', $role->password);
    }

    #[DataProvider('provideHalfPairs')]
    public function testHalfPairIsRefused(string $user, string $password): void
    {
        putenv(self::PREFIX.'_POSTGRES_USER='.$user);
        putenv(self::PREFIX.'_POSTGRES_PASSWORD='.$password);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be both set or both empty');

        DatabaseRole::fromEnv(self::PREFIX);
    }

    public static function provideHalfPairs(): iterable
    {
        yield 'user only' => ['databox', ''];
        yield 'password only' => ['', 's3cr3t'];
    }
}
