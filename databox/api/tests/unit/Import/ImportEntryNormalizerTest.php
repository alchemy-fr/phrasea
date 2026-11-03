<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Service\Import\Exception\InvalidImportEntryException;
use App\Service\Import\ImportEntryNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportEntryNormalizerTest extends TestCase
{
    public function testKnownFieldsAreKeptAndUnknownOnesIgnored(): void
    {
        $normalized = (new ImportEntryNormalizer())->normalize([
            'key' => 123,
            'externalId' => ' ext-1 ',
            'name' => 'Sunset',
            'path' => ' Sport / /Football/ ',
            'tags' => ['sea', 'sea', 'sky'],
            'privacy' => 'public',
            'attributes' => ['title' => 'Sunset', 'keywords' => ['sea', 'sky'], 'description:fr' => 'Coucher', 'empty' => null],
            'sourceUrl' => 'https://cdn.example.com/a.jpg',
            'sourceName' => 'a.jpg',
            'sourceType' => 'image/jpeg',
            'previewUrl' => 'https://cdn.example.com/a-preview.jpg',
            'renditions' => ['thumbnail' => 'http://cdn.example.com/a-thumb.jpg'],
            'futureField' => true,
        ]);

        $this->assertSame([
            'key' => '123',
            'externalId' => 'ext-1',
            'name' => 'Sunset',
            'path' => ['Sport', 'Football'],
            'sourceName' => 'a.jpg',
            'sourceType' => 'image/jpeg',
            'sourceUrl' => 'https://cdn.example.com/a.jpg',
            'previewUrl' => 'https://cdn.example.com/a-preview.jpg',
            'tags' => ['sea', 'sky'],
            'privacy' => 'public',
            'attributes' => ['title' => 'Sunset', 'keywords' => ['sea', 'sky'], 'description:fr' => 'Coucher'],
            'renditions' => ['thumbnail' => 'http://cdn.example.com/a-thumb.jpg'],
        ], $normalized);
    }

    /**
     * @return iterable<string, array{array, string}>
     */
    public static function provideInvalidEntries(): iterable
    {
        yield 'nothing to import' => [['key' => 'a', 'tags' => []], 'must have a "sourceUrl"'];
        yield 'empty renditions only' => [['renditions' => []], 'must have a "sourceUrl"'];
        yield 'collection and path' => [['name' => 'a', 'collection' => 'c', 'path' => 'A/B'], '"collection" and "path" cannot be used together'];
        yield 'empty path' => [['name' => 'a', 'path' => ' / '], '"path" must contain at least one collection name'];
        yield 'not a string' => [['name' => ['a']], '"name" must be a string'];
        yield 'not an HTTP URL' => [['sourceUrl' => 'file:///etc/passwd'], '"sourceUrl" must be an HTTP(S) URL'];
        yield 'invalid URL' => [['previewUrl' => 'https://'], '"previewUrl" must be an HTTP(S) URL'];
        yield 'invalid rendition URL' => [['renditions' => ['preview' => 'ftp://host/a.jpg']], '"renditions.preview" must be an HTTP(S) URL'];
        yield 'renditions not an object' => [['renditions' => ['https://cdn.example.com/a.jpg']], '"renditions" must be an object'];
        yield 'tags not a list' => [['name' => 'a', 'tags' => 'sea'], '"tags" must be a list'];
        yield 'invalid tag' => [['name' => 'a', 'tags' => [1]], '"tags" must be a list of tag IDs or names'];
        yield 'invalid privacy' => [['name' => 'a', 'privacy' => 1.5], '"privacy" must be an integer or a privacy label'];
        yield 'attributes not an object' => [['name' => 'a', 'attributes' => ['a', 'b']], '"attributes" must be an object'];
        yield 'nested attribute value' => [['name' => 'a', 'attributes' => ['title' => ['a' => 'b']]], 'Attribute "title"'];
    }

    #[DataProvider('provideInvalidEntries')]
    public function testInvalidEntry(array $entry, string $expectedMessage): void
    {
        $this->expectException(InvalidImportEntryException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new ImportEntryNormalizer())->normalize($entry);
    }
}
