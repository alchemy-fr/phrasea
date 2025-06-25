<?php

declare(strict_types=1);

namespace App\Storage;

use Alchemy\CoreBundle\Pusher\PusherManager;
use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;

final class RenditionManager
{
    private array $renditionsToDelete = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileManager $fileManager,
        private readonly PusherManager $pusherManager,
        private readonly PostFlushStack $postFlushStack,
    ) {
    }

    public function createOrReplaceRenditionByPath(
        Asset $asset,
        RenditionDefinition $definition,
        string $storage,
        string $path,
        ?string $type,
        ?int $size,
        ?string $originalName,
        ?string $buildHash,
        ?array $moduleHashes,
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
            $file,
            $buildHash,
            $moduleHashes,
        );
    }

    /**
     * @param bool $force Force replace a substitution
     */
    public function createOrReplaceRenditionFile(
        Asset $asset,
        RenditionDefinition $definition,
        File $file,
        ?string $buildHash,
        ?array $moduleHashes,
        bool $substituted = false,
        bool $locked = false,
        bool $force = false,
        ?bool $projection = null,
    ): AssetRendition {
        if (null === $asset->getSource() && $definition->isUseAsOriginal()) {
            $asset->setSource($file);
            $this->em->persist($asset);
        }

        $rendition = $this->getOrCreateRendition($asset, $definition);

        if ($rendition->isLocked()) {
            throw new \InvalidArgumentException(sprintf('Rendition "%s" is locked', $definition->getName()));
        }

        if ($rendition->isSubstituted() && !$force) {
            throw new \InvalidArgumentException(sprintf('Rendition "%s" is a substitution and cannot be replaced without the "force" option', $definition->getName()));
        }

        $rendition->setFile($file);
        $rendition->setBuildHash($buildHash);
        $rendition->setModuleHashes($moduleHashes);
        $rendition->setSubstituted($substituted);
        $rendition->setLocked($locked);
        $rendition->setProjection($projection);
        $this->em->persist($rendition);

        $this->postFlushStack->addBusMessage($this->pusherManager->createBusMessage(
            'assets',
            'rendition-update',
            [
                'assetId' => $asset->getId(),
                'definition' => $definition->getId(),
            ]
        ));

        return $rendition;
    }

    public function getOrCreateRendition(
        Asset $asset,
        RenditionDefinition $definition,
    ): AssetRendition {
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
            ->innerJoin('d.policy', 'p')
            ->andWhere('r.asset = :asset')
            ->andWhere('p.public = true')
            ->andWhere(sprintf('d.useAs%s = :as', ucfirst($as)))
            ->setParameters([
                'asset' => $assetId,
                'as' => true,
            ])
            ->addOrderBy('d.priority', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getRenditionDefinitionByName(string $workspaceId, string $name): RenditionDefinition
    {
        $definition = $this
            ->em
            ->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'name' => $name,
                'workspace' => $workspaceId,
            ]);

        if (!$definition instanceof RenditionDefinition) {
            throw new \InvalidArgumentException(sprintf('Rendition definition "%s" not found', $name));
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

    public function getRenditionDefinitionById(string $workspaceId, string $id): RenditionDefinition
    {
        $definition = $this
            ->em
            ->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'id' => $id,
                'workspace' => $workspaceId,
            ]);

        if (!$definition instanceof RenditionDefinition) {
            throw new \InvalidArgumentException(sprintf('Rendition definition "%s" not found', $id));
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
