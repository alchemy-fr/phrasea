<?php

declare(strict_types=1);

namespace App\Tests\Border\FileAnalyzer;

use App\Border\FileAnalyzer\Analyzer\FilenameAnalyzer;
use App\Entity\Core\File;
use PHPUnit\Framework\TestCase;

class FilenameAnalyzerTest extends TestCase
{
    /**
     * @dataProvider allowedPatternsProvider
     */
    public function testAllowedPatternsWithOrWithoutDelimiters(array $patterns, string $filename, bool $expectedSuccess): void
    {
        $output = (new FilenameAnalyzer())->analyzeFile($this->createFile($filename), null, [
            'allowed_patterns' => $patterns,
        ]);

        $this->assertSame($expectedSuccess, $output->isSuccessful());
    }

    public static function allowedPatternsProvider(): iterable
    {
        yield 'undelimited pattern, matching' => [['^PHOTO_.*'], 'PHOTO_001.jpg', true];
        yield 'undelimited pattern, not matching' => [['^PHOTO_.*'], 'IMG_001.jpg', false];
        yield 'delimited pattern, matching' => [['/^PHOTO_.*/'], 'PHOTO_001.jpg', true];
        yield 'delimited pattern with modifier, matching' => [['/^photo_.*/i'], 'PHOTO_001.jpg', true];
        yield 'delimited pattern, not matching' => [['/^PHOTO_.*/'], 'IMG_001.jpg', false];
        yield 'undelimited pattern containing a slash' => [['^a/b'], 'a/b.jpg', true];
        yield 'any of several patterns' => [['^IMG_', '^PHOTO_'], 'PHOTO_001.jpg', true];
    }

    public function testDisallowedPatternWithoutDelimiters(): void
    {
        $analyzer = new FilenameAnalyzer();

        $this->assertFalse($analyzer->analyzeFile($this->createFile('draft.tmp'), null, [
            'disallowed_patterns' => ['\.tmp$'],
        ])->isSuccessful());
        $this->assertTrue($analyzer->analyzeFile($this->createFile('final.jpg'), null, [
            'disallowed_patterns' => ['\.tmp$'],
        ])->isSuccessful());
    }

    public function testValidateConfigurationAcceptsPatternsWithOrWithoutDelimiters(): void
    {
        (new FilenameAnalyzer())->validateConfiguration([
            'allowed_patterns' => ['^PHOTO_.*', '/^IMG_/i'],
            'disallowed_patterns' => ['\.tmp$'],
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function testValidateConfigurationRejectsInvalidPattern(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid regex pattern "^PHOTO_(" in "allowed_patterns"');

        (new FilenameAnalyzer())->validateConfiguration([
            'allowed_patterns' => ['^PHOTO_('],
        ]);
    }

    private function createFile(string $filename): File
    {
        $file = new File();
        $file->setOriginalName($filename);
        $file->setType('image/jpeg');

        return $file;
    }
}
