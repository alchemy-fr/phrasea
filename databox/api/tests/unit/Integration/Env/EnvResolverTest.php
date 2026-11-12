<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Env;

use App\Integration\Env\EnvResolver;
use PHPUnit\Framework\TestCase;

final class EnvResolverTest extends TestCase
{
    private const array VARIABLES = [
        'PWI_UT_SYS_FOO' => 'bar',
        'PWI_UT_SYS_EMPTY' => '',
        'PWI_UT-BAD' => 'x',
        'UT_SYS_NOPREFIX' => 'x',
    ];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name => $value) {
            putenv($name.'='.$value);
        }
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::VARIABLES) as $name) {
            putenv($name);
        }
    }

    public function testTheSystemEnvNamesAreTheNonEmptyValidOnesWithoutPrefix(): void
    {
        // The container carries its own PWI_* variables: never assert the exact list
        $names = EnvResolver::getSystemEnvNames();

        $this->assertContains('UT_SYS_FOO', $names);
        $this->assertNotContains('UT_SYS_EMPTY', $names);
        $this->assertNotContains('UT-BAD', $names);
        $this->assertNotContains('UT_SYS_NOPREFIX', $names);
        $this->assertNotContains('PWI_UT_SYS_FOO', $names);

        $sorted = $names;
        sort($sorted, \SORT_STRING);
        $this->assertSame($sorted, $names);
    }

    public function testARemovedVariableIsNoLongerListed(): void
    {
        putenv('PWI_UT_SYS_FOO');

        $this->assertNotContains('UT_SYS_FOO', EnvResolver::getSystemEnvNames());
    }
}
