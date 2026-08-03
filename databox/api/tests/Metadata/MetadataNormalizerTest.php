<?php

declare(strict_types=1);

namespace App\Tests\Metadata;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Service\Metadata\MetadataNormalizer;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MetadataNormalizerTest extends KernelTestCase
{
    private MetadataManipulator $manipulator;
    private MetadataNormalizer $normalizer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->manipulator = static::getContainer()->get(MetadataManipulator::class);
        $this->normalizer = static::getContainer()->get(MetadataNormalizer::class);
    }

    public function testNormalizeGroupsByNamespaceAndUnwrapsValues(): void
    {
        $bag = new MetadataBag();
        $bag->add($this->createMeta('IPTC:Keywords', ['dog', 'cat', 'bird']));
        $bag->add($this->createMeta('IPTC:City', 'Paris'));
        $bag->add($this->createMeta('File:MIMEType', 'image/jpeg'));
        $bag->add($this->createMeta('System:FileName', 'photo.jpg'));

        $this->assertEquals([
            'IPTC' => [
                'Keywords' => ['dog', 'cat', 'bird'],
                'City' => ['Paris'],
            ],
            'File' => [
                'MIMEType' => ['image/jpeg'],
            ],
            'System' => [
                'FileName' => ['photo.jpg'],
            ],
        ], $this->normalizer->normalize($bag));
    }

    public function testDenormalizeRebuildsTagIdsAndSkipsSystemAndReadOnly(): void
    {
        $bag = $this->normalizer->denormalize([
            'IPTC' => [
                'Keywords' => ['dog', 'cat'], // multi + writable => kept
                'City' => ['Paris'],          // mono + writable => kept (first value)
            ],
            'File' => [
                'MIMEType' => ['image/jpeg'], // not writable => skipped
            ],
            'System' => [
                'FileName' => ['photo.jpg'],  // System namespace => skipped
            ],
        ]);

        $byId = $this->indexBag($bag);

        $this->assertSame(['IPTC:City', 'IPTC:Keywords'], $this->sortedKeys($byId));
        $this->assertSame(['dog', 'cat'], $byId['IPTC:Keywords']);
        $this->assertSame(['Paris'], $byId['IPTC:City']);
    }

    public function testRoundTripPreservesWritableTags(): void
    {
        $bag = new MetadataBag();
        $bag->add($this->createMeta('IPTC:Keywords', ['dog', 'cat', 'bird']));
        $bag->add($this->createMeta('IPTC:City', 'Paris'));

        $roundTripped = $this->indexBag(
            $this->normalizer->denormalize($this->normalizer->normalize($bag))
        );

        $this->assertSame(['dog', 'cat', 'bird'], $roundTripped['IPTC:Keywords']);
        $this->assertSame(['Paris'], $roundTripped['IPTC:City']);
    }

    private function createMeta(string $tagGroupId, string|array $value): Metadata
    {
        return $this->manipulator->createMetadata($tagGroupId)->setValue($value);
    }

    /**
     * @return array<string, array> map of tag-group id => values
     */
    private function indexBag(MetadataBag $bag): array
    {
        $byId = [];
        /** @var Metadata $meta */
        foreach ($bag as $meta) {
            $byId[$meta->getTagGroup()->getId()] = $meta->getValue()->asArray();
        }

        return $byId;
    }

    /**
     * @param array<string, mixed> $byId
     *
     * @return string[]
     */
    private function sortedKeys(array $byId): array
    {
        $keys = array_keys($byId);
        sort($keys);

        return $keys;
    }
}
