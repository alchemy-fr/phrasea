<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Api\Model\Output\AssetOutput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\File;
use App\Entity\Core\AssetRendition;
use App\Security\RenditionPermissionManager;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\CollectionVoter;
use Doctrine\ORM\EntityManagerInterface;

class AssetOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private EntityManagerInterface $em;
    private RenditionPermissionManager $renditionPermissionManager;

    public function __construct(EntityManagerInterface $em, RenditionPermissionManager $renditionPermissionManager)
    {
        $this->em = $em;
        $this->renditionPermissionManager = $renditionPermissionManager;
    }

    /**
     * @param Asset $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $user = $this->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : null;
        $groupIds = $user instanceof RemoteUser ? $user->getGroupIds() : [];

        $output = new AssetOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->setTitle($object->getTitle());
        $output->setPrivacy($object->getPrivacy());
        $output->setTags($object->getTags()->getValues());
        $output->setWorkspace($object->getWorkspace());

        $renditions = $this->em
            ->getRepository(AssetRendition::class)
            ->findAssetRenditions($object->getId());

        foreach ([
            'original',
            'preview',
            'thumbnail',
            'thumbnailActive',
                 ] as $type) {
            if (null !== $file = $this->getRenditionFile($renditions, $object, $type, $userId, $groupIds)) {
                $output->{'set'.ucfirst($type)}($file);
            }
        }

        $output->setCollections($object->getCollections()->map(function (CollectionAsset $collectionAsset): Collection {
            return $collectionAsset->getCollection();
        })->filter(function (Collection $collection): bool {
            return $this->isGranted(CollectionVoter::LIST, $collection);
        })->getValues());

        $output->setCapabilities([
            'canEdit' => $this->isGranted(AssetVoter::EDIT, $object),
            'canDelete' => $this->isGranted(AssetVoter::DELETE, $object),
            'canEditPermissions' => $this->isGranted(AssetVoter::EDIT_PERMISSIONS, $object),
        ]);

        return $output;
    }

    /**
     * @param AssetRendition[] $assetRenditions
     */
    private function getRenditionFile(array $assetRenditions, Asset $asset, string $type, ?string $userId, array $groupIds): ?File
    {
        foreach ($assetRenditions as $rendition) {
            if ($rendition->getDefinition()->{'isUseAs'.ucfirst($type)}()) {
                // Return the first viewable sub def for user
                if ($this->renditionPermissionManager->isGranted($asset, $rendition->getDefinition()->getClass(), $userId, $groupIds)) {
                    return $rendition->getFile();
                }
            }
        }

        return null;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AssetOutput::class === $to && $data instanceof Asset;
    }
}
