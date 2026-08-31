<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Subscriber;

use Alchemy\NotifierBundle\Subscriber\KeycloakUserMapper;
use PHPUnit\Framework\TestCase;

final class KeycloakUserMapperTest extends TestCase
{
    public function testItReadsTheFirstValueOfMultiValuedAttributes(): void
    {
        $info = (new KeycloakUserMapper())->map([
            'email' => 'jane@example.com',
            'attributes' => ['locale' => ['fr', 'en'], 'phoneNumber' => ['+33600000000']],
        ]);

        self::assertSame('fr', $info->locale);
        self::assertSame('+33600000000', $info->phoneNumber);
    }

    public function testDisplayNameFallsBackToUsernameThenEmail(): void
    {
        $mapper = new KeycloakUserMapper();

        self::assertSame('Jane Doe', $mapper->map(['firstName' => 'Jane', 'lastName' => 'Doe'])->displayName);
        self::assertSame('Jane', $mapper->map(['firstName' => 'Jane'])->displayName);
        self::assertSame('jdoe', $mapper->map(['username' => 'jdoe'])->displayName);
        self::assertSame('jane@example.com', $mapper->map(['email' => 'jane@example.com'])->displayName);
        self::assertNull($mapper->map([])->displayName);
    }
}
