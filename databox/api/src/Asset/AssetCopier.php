<?php

declare(strict_types=1);

namespace App\Asset;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Consumer\Handler\File\NewAssetFromBorderHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Security\RenditionPermissionManager;
use App\Storage\RenditionPathGenerator;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;

class AssetCopier
{
    private EventProducer $eventProducer;
    private EntityManagerInterface $em;
    private FileStorageManager $storageManager;
    private RenditionPathGenerator $pathGenerator;

    public const OPT_WITH_ATTRIBUTES = 'withAttributes';
    public const OPT_WITH_TAGS = 'withTags';

    private array $fileCopies = [];
    private RenditionPermissionManager $renditionPermissionManager;

    public function __construct(
        EventProducer $eventProducer,
        EntityManagerInterface $em,
        FileStorageManager $storageManager,
        RenditionPathGenerator $pathGenerator,
        RenditionPermissionManager $renditionPermissionManager
    ) {
        $this->eventProducer = $eventProducer;
        $this->em = $em;
        $this->storageManager = $storageManager;
        $this->pathGenerator = $pathGenerator;
        $this->renditionPermissionManager = $renditionPermissionManager;
    }

    public function copyAsset(
        string $userId,
        array $groupsId,
        Asset $asset,
        Workspace $workspace,
        ?Collection $collection,
        array $options = []
    ): void {
        $sameWorkspace = $asset->getWorkspaceId() === $workspace->getId();
        if (!$sameWorkspace) {
            if (!$asset->getFile()) {
                $options[self::OPT_WITH_TAGS] = false;
                $options[self::OPT_WITH_ATTRIBUTES] = false;
                $this->doCopyAsset(
                    $userId,
                    $groupsId,
                    $asset,
                    $workspace,
                    $collection,
                    $options
                );
                $this->em->flush();
            } else {
                $file = $this->copyFile($asset->getFile(), $workspace);
                $this->em->flush();

                $this->eventProducer->publish(NewAssetFromBorderHandler::createEvent(
                    $userId,
                    $file->getId(),
                    $collection ? [$collection->getId()] : [],
                    $asset->getTitle()
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

            $this->em->flush();
        }
    }

    private function doCopyAsset(
        string $userId,
        array $groupsId,
        Asset $asset,
        Workspace $workspace,
        ?Collection $collection,
        array $options = []
    ): void {
        $copy = new Asset();
        $copy->setOwnerId($userId);
        $copy->setTitle($asset->getTitle());
        $copy->setPrivacy($asset->getPrivacy());
        $copy->setLocale($asset->getLocale());
        $copy->setWorkspace($workspace);

        if ($collection instanceof Collection) {
            $copy->addToCollection($collection);
        }
        if ($asset->getFile()) {
            $copy->setFile($this->copyFile($asset->getFile(), $workspace));
        }

        foreach ($asset->getRenditions() as $rendition) {
            if ($this->renditionPermissionManager->isGranted($asset, $rendition->getDefinition()->getClass(),
                $userId,
                $groupsId
            )) {
                $this->copyRendition($rendition, $copy);
            }
        }

        if ($options[self::OPT_WITH_ATTRIBUTES] ?? false) {
            $attributes = $this->em->getRepository(Attribute::class)
                ->getAssetAttributes($asset);

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
        $copy->setCoordinates($attribute->getCoordinates());
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
        $copy->setReady($rendition->isReady());

        if ($rendition->getFile()) {
            $copy->setFile($this->copyFile($rendition->getFile(), $target->getWorkspace()));
        }

        $this->em->persist($copy);
    }

    private function copyFile(File $file, Workspace $workspace): File
    {
        if (isset($this->fileCopies[$file->getId()])) {
            return $this->fileCopies[$file->getId()];
        }

        $copy = new File();
        $copy->setType($file->getType());
        $copy->setWorkspace($workspace);
        $copy->setAlternateUrls($file->getAlternateUrls());
        $copy->setPathPublic($file->isPathPublic());
        $copy->setStorage($file->getStorage());
        $copy->setSize($file->getSize());

        if (File::STORAGE_S3_MAIN === $file->getStorage()) {
            $stream = $this->storageManager->getStream($file->getPath());
            $extension = strtolower(pathinfo($file->getPath(), PATHINFO_EXTENSION) ?? '');
            $path = $this->pathGenerator->generatePath($workspace->getId(), $extension);
            $this->storageManager->storeStream($path, $stream);
            $copy->setPath($path);
        } else {
            $copy->setPath($file->getPath());
        }

        $this->em->persist($copy);

        return $this->fileCopies[$file->getId()] = $copy;
    }
}
