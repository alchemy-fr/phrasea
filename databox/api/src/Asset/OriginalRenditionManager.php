<?php

declare(strict_types=1);

namespace App\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;

class OriginalRenditionManager
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly RenditionManager $renditionManager)
    {
    }

    /**
     * @return AssetRendition[]
     */
    public function assignFileToOriginalRendition(Asset $asset, File $file): array
    {
        $originalRenditionDefinitions = $this->em->getRepository(RenditionDefinition::class)
            ->findBy([
                'workspace' => $file->getWorkspace()->getId(),
                'pickSourceFile' => true,
            ]);

        $renditions = [];
        foreach ($originalRenditionDefinitions as $originalRenditionDefinition) {
            $origRendition = $this->renditionManager->getOrCreateRendition($asset, $originalRenditionDefinition);
            $origRendition->setFile($file);

            $this->em->persist($origRendition);
            $renditions[] = $origRendition;
        }

        return $renditions;
    }
}
