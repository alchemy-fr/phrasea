<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\AssetOutput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\CollectionVoter;

class AssetOutputDataTransformer extends AbstractSecurityDataTransformer
{
    /**
     * @param Asset $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new AssetOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->setTitle($object->getTitle());
        $output->setPrivacy($object->getPrivacy());
        $output->setTags($object->getTags()->getValues());
        $output->setWorkspace($object->getWorkspace());

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

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AssetOutput::class === $to && $data instanceof Asset;
    }
}
