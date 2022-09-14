<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class RenditionManager
{
    private EntityManagerInterface $em;
    private FileManager $fileManager;

    public function __construct(EntityManagerInterface $em, FileManager $fileManager)
    {
        $this->em = $em;
        $this->fileManager = $fileManager;
    }

    public function createOrReplaceRendition(
        Asset $asset,
        RenditionDefinition $definition,
        string $storage,
        string $path,
        ?string $type,
        ?int $size,
        ?string $originalName
    ): AssetRendition {
        $file = $this->fileManager->createFile(
            $storage,
            $path,
            $type,
            $size,
            $originalName,
            $asset->getWorkspace(),
        );

        return $this->createOrReplaceRenditionFile(
            $asset,
            $definition,
            $file
        );
    }

    public function createOrReplaceRenditionFile(
        Asset $asset,
        RenditionDefinition $definition,
        File $file
    ): AssetRendition
    {
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

    public function createOrReplaceRenditionFromSource(
        Asset $asset,
        RenditionDefinition $definition,
        string $src,
        ?string $type,
        ?string $extension,
        ?string $originalName
    ): AssetRendition {
        $file = $this->fileManager->createFileFromPath(
            $asset->getWorkspace(),
            $src,
            $type,
            $extension,
            $originalName
        );

        return $this->createOrReplaceRenditionFile($asset, $definition, $file);
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

    public function getRenditionDefinitionByName(Workspace $workspace, string $name): RenditionDefinition
    {
        $definition = $this->em->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'name' => $name,
                'workspace' => $workspace->getId(),
            ]);

        if (!$definition instanceof RenditionDefinition) {
            throw new InvalidArgumentException(sprintf('Rendition definition "%s" not found', $name));
        }

        return $definition;
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
