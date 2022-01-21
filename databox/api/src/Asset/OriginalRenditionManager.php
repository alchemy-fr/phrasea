<?php

declare(strict_types=1);

namespace App\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use Doctrine\ORM\EntityManagerInterface;

class OriginalRenditionManager
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function assignFileToOriginalRendition(Asset $asset, File $file): void
    {
        $originalRenditionDefinitions = $this->em->getRepository(RenditionDefinition::class)
            ->findBy([
                'workspace' => $file->getWorkspace()->getId(),
                'useAsOriginal' => true,
            ]);

        foreach ($originalRenditionDefinitions as $originalRenditionDefinition) {
            $origRendition = new AssetRendition();
            $origRendition->setAsset($asset);
            $origRendition->setFile($file);
            $origRendition->setDefinition($originalRenditionDefinition);
            $origRendition->setReady(true);

            $this->em->persist($origRendition);
        }
    }
}
