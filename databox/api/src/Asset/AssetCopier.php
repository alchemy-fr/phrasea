<?php

declare(strict_types=1);

namespace App\Asset;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\File\NewAssetFromBorder;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Security\RenditionPermissionManager;
use Doctrine\ORM\EntityManagerInterface;

class AssetCopier
{
    final public const string OPT_WITH_ATTRIBUTES = 'withAttributes';
    final public const string OPT_WITH_TAGS = 'withTags';

    private array $fileCopies = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RenditionPermissionManager $renditionPermissionManager,
        private readonly FileCopier $fileCopier,
        private readonly PostFlushStack $postFlushStack,
    ) {
    }

    public function copyAsset(
        string $userId,
        array $groupsId,
        Asset $asset,
        Workspace $workspace,
        ?Collection $collection,
        array $options = [],
    ): void {
        $sameWorkspace = $asset->getWorkspaceId() === $workspace->getId();
        if (!$sameWorkspace) {
            if (!$asset->getSource()) {
                $this->doCopyAsset(
                    $userId,
                    $groupsId,
                    $asset,
                    $workspace,
                    $collection,
                    $options
                );
            } else {
                $file = $this->copyFile($asset->getSource(), $workspace);
                $this->postFlushStack->addBusMessage(new NewAssetFromBorder(
                    $userId,
                    $file->getId(),
                    $collection ? [$collection->getId()] : [],
                    $asset->getTitle(),
                    $file->getFilename()
                ));
            }
        } else {
            $this->doCopyAsset(
                $userId,
                $groupsId,
                $asset,
                $workspace,
                $collection,
                $options
            );
        }

        $this->em->flush();
        $this->fileCopies = [];
    }

    private function doCopyAsset(
        string $userId,
        array $groupsId,
        Asset $asset,
        Workspace $workspace,
        ?Collection $collection,
        array $options = [],
    ): void {
        $sameWorkspace = $asset->getWorkspaceId() === $workspace->getId();
        $copy = new Asset();
        $copy->setOwnerId($userId);
        $copy->setTitle($asset->getTitle());
        $copy->setPrivacy($asset->getPrivacy());
        $copy->setLocale($asset->getLocale());
        $copy->setWorkspace($workspace);

        if (null !== $collection) {
            if ($collection->getWorkspaceId() !== $workspace->getId()) {
                throw new \InvalidArgumentException(sprintf('Failed to copy asset: Collection %s does not belong to workspace %s', $collection->getId(), $workspace->getId()));
            }
            $copy->addToCollection($collection);
        }
        if (null !== $asset->getSource()) {
            $copy->setSource($this->copyFile($asset->getSource(), $workspace));
        }

        if ($sameWorkspace) {
            foreach ($asset->getRenditions() as $rendition) {
                if ($this->renditionPermissionManager->isGranted($asset, $rendition->getDefinition()->getPolicy(),
                    $userId,
                    $groupsId
                )) {
                    $this->copyRendition($rendition, $copy);
                }
            }

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

    private function copyRendition(AssetRendition $rendition, Asset $target): void
    {
        $copy = new AssetRendition();
        $copy->setAsset($target);
        $copy->setDefinition($rendition->getDefinition());

        if (null !== $file = $rendition->getFile()) {
            $copy->setFile($this->copyFile($file, $target->getWorkspace()));
        }

        $this->em->persist($copy);
    }

    private function copyFile(File $file, Workspace $workspace): File
    {
        $key = $file->getId().':'.$workspace->getId();

        return $this->fileCopies[$key] ?? ($this->fileCopies[$key] = $this->fileCopier->copyFile($file, $workspace));
    }
}
