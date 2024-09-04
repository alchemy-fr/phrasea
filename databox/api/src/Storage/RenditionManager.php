<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use InvalidArgumentException;

class RenditionManager
{
    private array $renditionsToDelete = [];

    public function __construct(private readonly EntityManagerInterface $em, private readonly FileManager $fileManager)
    {
    }

    public function createOrReplaceRenditionByPath(
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
        File $file,
        ?string $buildHash
    ): AssetRendition {
        if (null === $asset->getSource() && $definition->isUseAsOriginal()) {
            $asset->setSource($file);
            $this->em->persist($asset);
        }

        $rendition = $this->getOrCreateRendition($asset, $definition);
        $rendition->setFile($file);
        $rendition->setBuildHash($buildHash);
        $this->em->persist($rendition);

        return $rendition;
    }

    public function getOrCreateRendition(Asset $asset, RenditionDefinition $definition): AssetRendition
    {
        if (null !== $assetRendition = $this->getAssetRenditionByDefinition($asset, $definition)) {
            return $assetRendition;
        }

        $rendition = new AssetRendition();
        $rendition->setAsset($asset);
        $rendition->setDefinition($definition);

        return $rendition;
    }

    public function getAssetRenditionByDefinition(Asset $asset, RenditionDefinition $definition): ?AssetRendition
    {
        $renditions = $asset->getRenditions();
        $collectionReady = !$renditions instanceof PersistentCollection || $renditions->isInitialized();
        if ($collectionReady) {
            foreach ($renditions as $rendition) {
                if ($rendition->getDefinition() === $definition) {
                    unset($this->renditionsToDelete[$rendition->getId()]);

                    return $rendition;
                }
            }
        }

        $rendition = $this->em->getRepository(AssetRendition::class)
            ->findOneBy([
                'asset' => $asset->getId(),
                'definition' => $definition->getId(),
            ]);

        if ($rendition instanceof AssetRendition) {
            unset($this->renditionsToDelete[$rendition->getId()]);

            return $rendition;
        }

        return null;
    }

    public function getAssetRenditionByName(string $assetId, string $renditionName): ?AssetRendition
    {
        return $this->em
            ->createQueryBuilder()
            ->select('r')
            ->from(AssetRendition::class, 'r')
            ->innerJoin('r.definition', 'd')
            ->andWhere('r.asset = :asset')
            ->andWhere('d.name = :name')
            ->setParameters([
                'asset' => $assetId,
                'name' => $renditionName,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getAssetRenditionUsedAs(string $as, string $assetId): ?AssetRendition
    {
        return $this->em
            ->createQueryBuilder()
            ->select('r')
            ->from(AssetRendition::class, 'r')
            ->innerJoin('r.definition', 'd')
            ->andWhere('r.asset = :asset')
            ->andWhere(sprintf('d.useAs%s = :as', ucfirst($as)))
            ->setParameters([
                'asset' => $assetId,
                'as' => true,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getRenditionDefinitionByName(Workspace $workspace, string $name): RenditionDefinition
    {
        $definition = $this
            ->em
            ->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'name' => $name,
                'workspace' => $workspace->getId(),
            ]);

        if (!$definition instanceof RenditionDefinition) {
            throw new InvalidArgumentException(sprintf('Rendition definition "%s" not found', $name));
        }

        return $definition;
    }

    public function getRenditionDefinitions(string $workspaceId): array
    {
        return $this
            ->em
            ->getRepository(RenditionDefinition::class)
            ->findBy([
                'workspace' => $workspaceId,
            ]);
    }

    public function getRenditionDefinitionById(Workspace $workspace, string $id): RenditionDefinition
    {
        $definition = $this
            ->em
            ->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'id' => $id,
                'workspace' => $workspace->getId(),
            ]);

        if (!$definition instanceof RenditionDefinition) {
            throw new InvalidArgumentException(sprintf('Rendition definition "%s" not found', $id));
        }

        return $definition;
    }

    public function resetAssetRenditions(Asset $asset): void
    {
        $renditions = $asset->getRenditions();

        foreach ($renditions as $rendition) {
            $this->renditionsToDelete[$rendition->getId()] = true;
        }
    }

    public function deleteScheduledRenditions(): void
    {
        $keys = array_keys($this->renditionsToDelete);
        $this->renditionsToDelete = [];

        foreach ($keys as $key) {
            $rendition = $this->em->find(AssetRendition::class, $key);
            if ($rendition instanceof AssetRendition) {
                $this->em->remove($rendition);
            }
        }
    }
}
