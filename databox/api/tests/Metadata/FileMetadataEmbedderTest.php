<?php

declare(strict_types=1);

namespace App\Tests\Metadata;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Service\Metadata\FileMetadataEmbedder;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FileMetadataEmbedderTest extends KernelTestCase
{
    private const array READ = [
        'IPTC' => [
            'Keywords' => ['dog', 'cat'],
            'City' => ['Paris'],
        ],
    ];

    private FileMetadataEmbedder $embedder;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->embedder = static::getContainer()->get(FileMetadataEmbedder::class);
    }

    public function testNothingIsBuiltWithoutFile(): void
    {
        $this->assertNull($this->embedder->buildMetadataBag(null));
    }

    public function testMetadataReadFromTheFileAreNeverWrittenBack(): void
    {
        $file = new File();
        $file->setMetadata(self::READ);

        $this->assertNull($this->embedder->buildMetadataBag($file));
    }

    public function testOnlyOverriddenMetadataAreEmbedded(): void
    {
        $file = new File();
        $file->setMetadata(self::READ);
        $file->setMetadataValue('IPTC:City', 'Lyon');

        $bag = $this->embedder->buildMetadataBag($file);

        $this->assertInstanceOf(MetadataBag::class, $bag);
        $this->assertSame(['IPTC:City' => ['Lyon']], $this->indexBag($bag));
    }

    public function testMultiValuedOverrideKeepsEveryValue(): void
    {
        $file = new File();
        $file->setMetadata(self::READ);
        $file->setMetadataValue('IPTC:Keywords', 'bird', append: true);

        $bag = $this->embedder->buildMetadataBag($file);

        $this->assertInstanceOf(MetadataBag::class, $bag);
        $this->assertSame(['IPTC:Keywords' => ['dog', 'cat', 'bird']], $this->indexBag($bag));
    }

    public function testSystemAndReadOnlyTagsAreSkipped(): void
    {
        $file = new File();
        $file->setMetadataValue('System:FileName', 'renamed.jpg');
        $file->setMetadataValue('File:MIMEType', 'image/jpeg');

        $this->assertNull($this->embedder->buildMetadataBag($file));
    }

    public function testUnknownTagIsSkippedInsteadOfFailingTheWholeBag(): void
    {
        $file = new File();
        $file->setMetadataValue('IPTC:NotATag', 'whatever');
        $file->setMetadataValue('IPTC:City', 'Lyon');

        $bag = $this->embedder->buildMetadataBag($file);

        $this->assertInstanceOf(MetadataBag::class, $bag);
        $this->assertSame(['IPTC:City' => ['Lyon']], $this->indexBag($bag));
    }

    public function testTheOverridesAreEmbeddedWhenTheSourceItselfIsExported(): void
    {
        $file = new File();
        $file->setMetadata(self::READ);
        $file->setMetadataValue('IPTC:City', 'Lyon');

        $asset = new Asset();
        $asset->setSource($file);

        $bag = $this->embedder->buildExportedFileMetadataBag($asset, $file);

        $this->assertInstanceOf(MetadataBag::class, $bag);
        $this->assertSame(['IPTC:City' => ['Lyon']], $this->indexBag($bag));
    }

    public function testTheOverridesNeverReachADerivedRenditionFile(): void
    {
        $source = new File();
        $source->setMetadata(self::READ);
        $source->setMetadataValue('IPTC:City', 'Lyon');

        $asset = new Asset();
        $asset->setSource($source);

        $renditionFile = new File();

        $this->assertNull(
            $this->embedder->buildExportedFileMetadataBag($asset, $renditionFile),
            'a rendition is a derived file: the metadata set on the original do not belong in it',
        );
    }

    public function testNothingIsEmbeddedForAnAssetWithoutSource(): void
    {
        $this->assertNull($this->embedder->buildExportedFileMetadataBag(new Asset(), new File()));
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
