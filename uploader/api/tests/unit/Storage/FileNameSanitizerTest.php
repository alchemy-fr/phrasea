<?php

declare(strict_types=1);

namespace App\Tests\Unit\Storage;

use App\Storage\FileNameSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileNameSanitizerTest extends TestCase
{
    public static function fileNameProvider(): iterable
    {
        yield 'plain' => ['photo.jpg', 'photo.jpg'];
        yield 'unix path' => ['../../etc/passwd', 'passwd'];
        yield 'windows path' => ['..\\..\\evil.exe', 'evil.exe'];
        yield 'control characters' => ["pho\x00to\r\n.jpg", 'photo.jpg'];
        yield 'surrounding spaces' => ['  photo.jpg ', 'photo.jpg'];
        yield 'empty' => ['', 'fallback'];
        yield 'directory only' => ['a/b/', 'fallback'];
        yield 'dots only' => ['..', 'fallback'];
        yield 'invalid UTF-8' => ["caf\xe9.jpg", 'caf?.jpg'];
        yield 'too long, extension kept' => [str_repeat('a', 300).'.jpeg', str_repeat('a', 250).'.jpeg'];
        yield 'too long, multibyte characters not cut' => [str_repeat('é', 200).'.jpg', str_repeat('é', 125).'.jpg'];
        yield 'too long without extension' => [str_repeat('a', 300), str_repeat('a', 255)];
        yield 'too long, too long to be an extension' => ['a.'.str_repeat('b', 300), 'a.'.str_repeat('b', 253)];
    }

    #[DataProvider('fileNameProvider')]
    public function testSanitize(string $name, string $expected): void
    {
        $sanitized = FileNameSanitizer::sanitize($name, 'fallback');

        $this->assertSame($expected, $sanitized);
        $this->assertLessThanOrEqual(FileNameSanitizer::MAX_LENGTH, strlen($sanitized));
        $this->assertTrue(mb_check_encoding($sanitized, 'UTF-8'));
    }
}
