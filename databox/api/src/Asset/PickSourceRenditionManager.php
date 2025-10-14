<?php

declare(strict_types=1);

namespace App\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;

readonly class PickSourceRenditionManager
{
    public function __construct(private EntityManagerInterface $em, private RenditionManager $renditionManager)
    {
    }

    /**
     * @return AssetRendition[]
     */
    public function assignFileToOriginalRendition(Asset $asset, File $file): array
    {
        $definitions = $this->em->getRepository(RenditionDefinition::class)
            ->findBy([
                'workspace' => $file->getWorkspace()->getId(),
                'buildMode' => RenditionDefinition::BUILD_MODE_PICK_SOURCE,
            ]);

        $renditions = [];
        foreach ($definitions as $definition) {
            $rendition = $this->renditionManager->getOrCreateRendition($asset, $definition);
            $rendition->setFile($file);

            $this->em->persist($rendition);
            $renditions[] = $rendition;
        }

        return $renditions;
    }
}
