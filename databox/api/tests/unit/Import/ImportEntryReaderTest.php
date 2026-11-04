<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Entity\Core\AssetImport;
use App\Service\Import\Exception\ImportFileException;
use App\Service\Import\ImportEntryReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportEntryReaderTest extends TestCase
{
    public function testNdjsonEntriesAreNumberedByLineAndBlankLinesIgnored(): void
    {
        $entries = $this->read(AssetImport::FORMAT_NDJSON, "\xEF\xBB\xBF{\"key\":\"a\"}\n\n  \r\n{\"key\":\"b\"}\r\n{}\n{\"key\":\"c\"}");

        $this->assertSame([
            1 => ['key' => 'a'],
            4 => ['key' => 'b'],
            5 => [],
            6 => ['key' => 'c'],
        ], $entries);
    }

    public function testInvalidNdjsonLinesAreReportedWithoutStoppingTheFile(): void
    {
        $entries = $this->read(AssetImport::FORMAT_NDJSON, "{\"key\":\"a\"}\n{not json\n[1,2]\n\"text\"\n{\"key\":\"b\"}\n");

        $this->assertSame(['key' => 'a'], $entries[1]);
        $this->assertStringStartsWith('Invalid JSON: ', $entries[2]);
        $this->assertSame('The entry must be a JSON object', $entries[3]);
        $this->assertSame('The entry must be a JSON object', $entries[4]);
        $this->assertSame(['key' => 'b'], $entries[5]);
    }

    public function testJsonListEntriesAreNumberedFromOne(): void
    {
        $entries = $this->read(AssetImport::FORMAT_JSON, '[{"key":"a"}, {"key":"b"}, 42]');

        $this->assertSame([
            1 => ['key' => 'a'],
            2 => ['key' => 'b'],
            3 => 'The entry must be a JSON object',
        ], $entries);
    }

    public function testJsonObjectWithAnAssetsList(): void
    {
        $entries = $this->read(AssetImport::FORMAT_JSON, '{"assets": [{"key":"a"}], "version": 1}');

        $this->assertSame([1 => ['key' => 'a']], $entries);
    }

    public function testEmptyJsonList(): void
    {
        $this->assertSame([], $this->read(AssetImport::FORMAT_JSON, '[]'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidJsonFiles(): iterable
    {
        yield 'invalid JSON' => ['[{"key": "a"', 'Invalid JSON: '];
        yield 'object without assets' => ['{"key": "a"}', 'The JSON file must contain a list of entries'];
        yield 'assets not a list' => ['{"assets": {"key": "a"}}', 'The JSON file must contain a list of entries'];
        yield 'scalar' => ['"text"', 'The JSON file must contain a list of entries'];
    }

    #[DataProvider('provideInvalidJsonFiles')]
    public function testInvalidJsonFileFailsTheWholeImport(string $content, string $expectedMessage): void
    {
        $this->expectException(ImportFileException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->read(AssetImport::FORMAT_JSON, $content);
    }

    public function testJsonFileAboveTheMaximumSizeMustUseNdjson(): void
    {
        $content = json_encode(array_fill(0, 100, ['key' => 'some-key']));

        $this->expectException(ImportFileException::class);
        $this->expectExceptionMessage('use the NDJSON format');

        $this->read(AssetImport::FORMAT_JSON, $content, strlen($content) - 1);
    }

    public function testNdjsonHasNoSizeLimit(): void
    {
        $content = str_repeat("{\"key\":\"some-key\"}\n", 100);

        $this->assertCount(100, $this->read(AssetImport::FORMAT_NDJSON, $content, 10));
    }

    public function testUnsupportedFormat(): void
    {
        $this->expectException(ImportFileException::class);

        $this->read('csv', 'a,b');
    }

    private function read(string $format, string $content, int $jsonMaxSize = 1024 * 1024): array
    {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, $content);
        rewind($stream);

        try {
            return iterator_to_array((new ImportEntryReader($jsonMaxSize))->iterate($stream, $format));
        } finally {
            fclose($stream);
        }
    }
}
