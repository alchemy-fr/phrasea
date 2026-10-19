<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Duplicate;

use App\Service\Duplicate\CopyNameGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CopyNameGeneratorTest extends TestCase
{
    /**
     * @param string[] $taken
     */
    #[DataProvider('getCases')]
    public function testGenerate(string $name, array $taken, string $expected): void
    {
        $generator = new CopyNameGenerator();

        $this->assertSame($expected, $generator->generate(
            $name,
            fn (string $candidate): bool => in_array($candidate, $taken, true),
        ));
    }

    public static function getCases(): array
    {
        return [
            'first copy' => ['Title', ['Title'], 'Title (copy)'],
            'second copy' => ['Title', ['Title', 'Title (copy)'], 'Title (copy 2)'],
            'skips taken numbers' => ['Title', ['Title', 'Title (copy)', 'Title (copy 2)', 'Title (copy 3)'], 'Title (copy 4)'],
            'copy of a copy restarts from the base name' => ['Title (copy)', ['Title', 'Title (copy)'], 'Title (copy 2)'],
            'copy of a numbered copy' => ['Title (copy 2)', ['Title', 'Title (copy)', 'Title (copy 2)'], 'Title (copy 3)'],
            'free first copy of a copy' => ['Title (copy 5)', ['Title (copy 5)'], 'Title (copy)'],
            'not a copy suffix' => ['Title (copyright)', ['Title (copyright)'], 'Title (copyright) (copy)'],
            'suffix only' => ['(copy)', ['(copy)'], '(copy) (copy)'],
            'trimmed' => [' Title ', [], 'Title (copy)'],
        ];
    }

    public function testCallsIsTakenUntilAFreeName(): void
    {
        $generator = new CopyNameGenerator();
        $candidates = [];

        $name = $generator->generate('Title', function (string $candidate) use (&$candidates): bool {
            $candidates[] = $candidate;

            return count($candidates) < 3;
        });

        $this->assertSame('Title (copy 3)', $name);
        $this->assertSame(['Title (copy)', 'Title (copy 2)', 'Title (copy 3)'], $candidates);
    }

    public function testRespectsTheMaxLength(): void
    {
        $generator = new CopyNameGenerator();

        $name = $generator->generate(str_repeat('a', 20), fn (string $candidate): bool => false, 20);
        $this->assertSame(str_repeat('a', 13).' (copy)', $name);
        $this->assertSame(20, mb_strlen($name));

        $name = $generator->generate(str_repeat('é', 20), fn (string $candidate): bool => !str_ends_with($candidate, '(copy 12)'), 20);
        $this->assertSame(str_repeat('é', 10).' (copy 12)', $name);

        $this->assertSame('Short (copy)', $generator->generate('Short', fn (string $candidate): bool => false, 20));
    }

    public function testTheTruncatedBaseIsRightTrimmed(): void
    {
        $generator = new CopyNameGenerator();

        $this->assertSame('ab (copy)', $generator->generate('ab cdef', fn (string $candidate): bool => false, 10));
    }
}
