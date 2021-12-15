<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;

class RenditionManager
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function createFile(
        string $storage,
        string $path,
        string $type,
        int $size,
        Workspace $workspace
    ): File
    {
        $file = new File();
        $file->setStorage($storage);
        $file->setType($type);
        $file->setSize($size);
        $file->setPath($path);
        $file->setWorkspace($workspace);

        $this->em->persist($file);

        return $file;
    }

    public function createOrReplaceRendition(
        Asset $asset,
        RenditionDefinition $definition,
        string $storage,
        string $path,
        string $type,
        int $size
    ): AssetRendition {
        $file = $this->createFile(
            $storage,
            $path,
            $type,
            $size,
            $asset->getWorkspace(),
        );

        if (null === $asset->getFile() && $definition->isUseAsOriginal()) {
            $asset->setFile($file);
            $this->em->persist($asset);
        }

        $rendition = $this->em->getRepository(AssetRendition::class)
            ->findOneBy([
                'asset' => $asset->getId(),
                'definition' => $definition->getId(),
            ]);

        if (!$rendition instanceof AssetRendition) {
            $rendition = new AssetRendition();
            $rendition->setAsset($asset);
            $rendition->setDefinition($definition);
        } else {
            // TODO remove old rendition file
        }

        $rendition->setFile($file);
        $rendition->setReady(true);

        $this->em->persist($rendition);

        return $rendition;
    }

    public function getAssetFromId(string $id): ?Asset
    {
        return $this->em->find(Asset::class, $id);
    }

    public function getDefinitionFromId(Workspace $workspace, string $id): ?RenditionDefinition
    {
        return $this->em->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'workspace' => $workspace->getId(),
                'id' => $id,
            ]);
    }

    public function getDefinitionFromName(Workspace $workspace, string $name): ?RenditionDefinition
    {
        return $this->em->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'workspace' => $workspace->getId(),
                'name' => $name,
            ]);
    }
}
