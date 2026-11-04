<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Subscriber;

use Alchemy\NotifierBundle\Subscriber\UserDirectoryInterface;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use PHPUnit\Framework\TestCase;

final class UserDirectoryRegistryTest extends TestCase
{
    public function testNullFallsBackToTheDefaultDirectory(): void
    {
        $registry = new UserDirectoryRegistry([$this->directory('a', 'A'), $this->directory('b', 'B')], 'b');

        self::assertSame('b', $registry->get(null)->getName());
        self::assertSame('a', $registry->get('a')->getName());
        self::assertSame('b', $registry->getDefaultName());
    }

    public function testChoicesAreNameToLabel(): void
    {
        $registry = new UserDirectoryRegistry([$this->directory('a', 'A'), $this->directory('b', 'B')], 'a');

        self::assertSame(['a' => 'A', 'b' => 'B'], $registry->getChoices());
    }

    public function testUnknownDirectoryThrows(): void
    {
        $registry = new UserDirectoryRegistry([$this->directory('a', 'A')], 'a');

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('nope');
    }

    private function directory(string $name, string $label): UserDirectoryInterface
    {
        return new class($name, $label) implements UserDirectoryInterface {
            public function __construct(private readonly string $name, private readonly string $label)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getLabel(): string
            {
                return $this->label;
            }

            public function iterate(): iterable
            {
                return [];
            }
        };
    }
}
