<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Api\Model\Output\AssetOutput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\File;
use App\Entity\Core\SubDefinition;
use App\Security\SubDefinitionPermissionManager;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\CollectionVoter;
use Doctrine\ORM\EntityManagerInterface;

class AssetOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private EntityManagerInterface $em;
    private SubDefinitionPermissionManager $subDefinitionPermissionManager;

    public function __construct(EntityManagerInterface $em, SubDefinitionPermissionManager $subDefinitionPermissionManager)
    {
        $this->em = $em;
        $this->subDefinitionPermissionManager = $subDefinitionPermissionManager;
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

        $subDefs = $this->em
            ->getRepository(SubDefinition::class)
            ->findAssetSubDefs($object->getId());

        foreach ([
            'original',
            'preview',
            'thumbnail',
            'thumbnailActive',
                 ] as $type) {
            if (null !== $file = $this->getSubDefFile($subDefs, $object, $type, $userId, $groupIds)) {
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
     * @param SubDefinition[] $subDefs
     */
    private function getSubDefFile(array $subDefs, Asset $asset, string $type, ?string $userId, array $groupIds): ?File
    {
        foreach ($subDefs as $subDef) {
            if ($subDef->getSpecification()->{'isUseAs'.ucfirst($type)}()) {
                // Return the first viewable sub def for user
                if ($this->subDefinitionPermissionManager->isGranted($asset, $subDef->getSpecification()->getClass(), $userId, $groupIds)) {
                    return $subDef->getFile();
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
