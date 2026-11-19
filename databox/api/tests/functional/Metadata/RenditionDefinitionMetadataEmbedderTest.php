<?php

declare(strict_types=1);

namespace App\Tests\Functional\Metadata;

use App\Entity\Core\RenditionDefinition;
use App\Service\Metadata\RenditionDefinitionMetadataEmbedder;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RenditionDefinitionMetadataEmbedderTest extends KernelTestCase
{
    private RenditionDefinitionMetadataEmbedder $embedder;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->embedder = static::getContainer()->get(RenditionDefinitionMetadataEmbedder::class);
    }

    public function testTheHardcodedMetadataAreEmbedded(): void
    {
        $definition = new RenditionDefinition();
        $definition->setMetadata([
            'IPTC:CopyrightNotice' => 'ACME',
            'XMP-dc:Rights' => 'Me',
        ]);

        $bag = $this->embedder->buildMetadataBag($definition);

        $this->assertInstanceOf(MetadataBag::class, $bag);
        $this->assertSame([
            'IPTC:CopyrightNotice' => ['ACME'],
            'XMP-dc:Rights' => ['Me'],
        ], $this->indexBag($bag));
    }

    public function testTagsNotAllowedToBeWrittenAreSkipped(): void
    {
        $definition = new RenditionDefinition();
        // saved before the tags were checked on input
        $definition->setMetadata([
            'File:HardLink' => '/srv/app/var/cache/link.php',
            'File:SymLink' => '/srv/app/var/cache/link.php',
            'system:Directory' => '/tmp',
            'SYSTEM:FileName' => 'renamed.jpg',
            'IPTC:CopyrightNotice' => 'ACME',
        ]);

        $bag = $this->embedder->buildMetadataBag($definition);

        $this->assertInstanceOf(MetadataBag::class, $bag);
        $this->assertSame(['IPTC:CopyrightNotice' => ['ACME']], $this->indexBag($bag));
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
}
