<?php

declare(strict_types=1);

namespace App\Asset;

use App\Attribute\AttributeDataExporter;
use App\Consumer\Handler\Asset\NewAssetIntegrationsHandler;
use App\Consumer\Handler\File\ReadMetadataHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class AssetManager
{
    private AttributeDataExporter $attributeDataExporter;
    private OriginalRenditionManager $originalRenditionManager;
    private EntityManagerInterface $em;
    private EventProducer $eventProducer;

    public function __construct(
        AttributeDataExporter $attributeDataExporter,
        OriginalRenditionManager $originalRenditionManager,
        EntityManagerInterface $em,
        EventProducer $eventProducer
    )
    {
        $this->attributeDataExporter = $attributeDataExporter;
        $this->originalRenditionManager = $originalRenditionManager;
        $this->em = $em;
        $this->eventProducer = $eventProducer;
    }

    public function assignNewAssetSourceFile(Asset $asset, File $file, ?array $formData = [], ?string $locale = null): void
    {
        if ($asset->getWorkspaceId() !== $file->getWorkspaceId()) {
            throw new InvalidArgumentException(sprintf('Asset and File are not in the same workspace'));
        }

        $asset->setSource($file);
        $asset->setPendingUploadToken(null);

        if (!empty($formData)) {
            $this->attributeDataExporter->importAttributes($asset, $formData, $locale);
        }

        $this->originalRenditionManager->assignFileToOriginalRendition($asset, $file);

        $this->em->persist($asset);
        $this->em->flush();

        $this->eventProducer->publish(ReadMetadataHandler::createEvent(
            $file->getId()
        ));

        $this->eventProducer->publish(NewAssetIntegrationsHandler::createEvent($asset->getId()));
    }
}
