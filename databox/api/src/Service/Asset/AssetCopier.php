<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\File\NewAssetFromBorder;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Service\Asset\Attribute\AssetNameResolver;
use Doctrine\ORM\EntityManagerInterface;

class AssetCopier
{
    final public const string OPT_WITH_ATTRIBUTES = 'withAttributes';
    final public const string OPT_WITH_TAGS = 'withTags';

    private array $fileCopies = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileCopier $fileCopier,
        private readonly PostFlushStack $postFlushStack,
        private readonly AssetNameResolver $assetNameResolver,
    ) {
    }

    public function copyAsset(
        string $userId,
        Asset $asset,
        Workspace $workspace,
        ?Collection $collection,
        array $allowedRenditions,
        array $options = [],
        ?array $relationExtraMetadata = null,
    ): void {
        try {
            $sameWorkspace = $asset->getWorkspaceId() === $workspace->getId();
            if (!$sameWorkspace) {
                if (!$asset->getSource()) {
                    $this->doCopyAsset(
                        $userId,
                        $asset,
                        $workspace,
                        $collection,
                        $allowedRenditions,
                        $options,
                        $relationExtraMetadata,
                    );
                } else {
                    $file = $this->copyFile($asset->getSource(), $workspace);
                    $this->postFlushStack->addBusMessage(new NewAssetFromBorder(
                        $userId,
                        $file->getId(),
                        $collection ? [$collection->getId()] : [],
                        $this->assetNameResolver->resolveNameAsString($asset),
                        $file->getFileName(),
                        relationExtraMetadata: $relationExtraMetadata,
                    ));
                }
            } else {
                $this->doCopyAsset(
                    $userId,
                    $asset,
                    $workspace,
                    $collection,
                    $allowedRenditions,
                    $options,
                    $relationExtraMetadata,
                );
            }

            $this->em->flush();
        } finally {
            $this->fileCopies = [];
        }
    }

    private function doCopyAsset(
        string $userId,
        Asset $asset,
        Workspace $workspace,
        ?Collection $collection,
        array $allowedRenditions,
        array $options,
        ?array $relationExtraMetadata,
    ): void {
        $sameWorkspace = $asset->getWorkspaceId() === $workspace->getId();
        $copy = new Asset();
        $copy->setOwnerId($userId);
        $copy->setPrivacy($asset->getPrivacy());
        $copy->setLocale($asset->getLocale());
        $copy->setWorkspace($workspace);

        if (null !== $collection) {
            if ($collection->getWorkspaceId() !== $workspace->getId()) {
                throw new \InvalidArgumentException(sprintf('Failed to copy asset: Collection %s does not belong to workspace %s', $collection->getId(), $workspace->getId()));
            }
            $copy->addToCollection($collection, assignReferenceIfNull: true, extraMetadata: $relationExtraMetadata);
        }
        if (null !== $asset->getSource()) {
            $copy->setSource($this->copyFile($asset->getSource(), $workspace));
        }

        if ($sameWorkspace) {
            $this->copyRenditions($asset, $copy, $allowedRenditions);

            if ($options[self::OPT_WITH_ATTRIBUTES] ?? false) {
                $attributes = $this->em->getRepository(Attribute::class)
                    ->getCachedAssetAttributes($asset->getId());

                foreach ($attributes as $attr) {
                    $this->copyAttribute($attr, $copy);
                }
            }

            if ($options[self::OPT_WITH_TAGS] ?? false) {
                $tags = $asset->getTags();
                foreach ($tags as $tag) {
                    $copy->addTag($tag);
                }
            }
        }

        $this->em->persist($copy);
    }

    private function copyAttribute(Attribute $attribute, Asset $target): void
    {
        $copy = new Attribute();
        $copy->setDefinition($attribute->getDefinition());
        $copy->setAsset($target);
        $copy->setOrigin($attribute->getOrigin());
        $copy->setPosition($attribute->getPosition());
        $copy->setStatus($attribute->getStatus());
        $copy->setValue($attribute->getValue());
        $copy->setConfidence($attribute->getConfidence());
        $copy->setCreatedAt($attribute->getCreatedAt());
        $copy->setAssetAnnotations($attribute->getAssetAnnotations());
        $copy->setLocale($attribute->getLocale());
        $copy->setOriginUserId($attribute->getOriginUserId());
        $copy->setOriginVendor($attribute->getOriginVendor());
        $copy->setOriginVendorContext($attribute->getOriginVendorContext());
        $copy->setUpdatedAt($attribute->getUpdatedAt());

        $this->em->persist($copy);
    }

    private function copyRenditions(Asset $asset, Asset $target, array $allowedRenditions): void
    {
        $renditions = [];
        foreach ($asset->getRenditions() as $rendition) {
            $definition = $rendition->getDefinition();
            if (null === $definition || in_array($definition->getId(), $allowedRenditions, true)) {
                $renditions[$rendition->getId()] = $rendition;
            }
        }

        // A rendition built from a rendition that is not copied could not be rebuilt on the copy
        do {
            $count = count($renditions);
            $renditions = array_filter(
                $renditions,
                fn (AssetRendition $rendition): bool => null === ($sourceId = $rendition->getSourceRenditionId()) || isset($renditions[$sourceId])
            );
        } while (count($renditions) !== $count);

        $copies = array_map(fn (AssetRendition $rendition): AssetRendition => $this->copyRendition($rendition, $target), $renditions);

        foreach ($copies as $copy) {
            if (null !== $sourceId = $copy->getSourceRenditionId()) {
                $copy->setBuildOptions([
                    AssetRendition::OPTION_SOURCE_RENDITION_ID => $copies[$sourceId]->getId(),
                ] + $copy->getBuildOptions());
            }
        }
    }

    private function copyRendition(AssetRendition $rendition, Asset $target): AssetRendition
    {
        $copy = new AssetRendition();
        $copy->setAsset($target);
        $copy->setDefinition($rendition->getDefinition());
        $copy->setName($rendition->isDynamic() ? $rendition->getName() : null);
        $copy->setBuildDefinition($rendition->getBuildDefinition());
        $copy->setBuildOptions($rendition->getBuildOptions());

        if (null !== $file = $rendition->getFile()) {
            $copy->setFile($this->copyFile($file, $target->getWorkspace()));
        }

        $this->em->persist($copy);

        return $copy;
    }

    private function copyFile(File $file, Workspace $workspace): File
    {
        if ($file->getWorkspaceId() === $workspace->getId()) {
            return $file;
        }

        $key = $file->getId().':'.$workspace->getId();

        return $this->fileCopies[$key] ?? ($this->fileCopies[$key] = $this->fileCopier->copyFile($file, $workspace));
    }
}
