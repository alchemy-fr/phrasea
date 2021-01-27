<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\AssetOutput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
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
        $output->setPublic($object->isPublic());
        $output->setTags($object->getTags()->getValues());

        $output->setCollections($object->getCollections()->map(function (CollectionAsset $collectionAsset): Collection {
            return $collectionAsset->getCollection();
        })->filter(function (Collection $collection): bool {
            return $this->isGranted(CollectionVoter::READ, $collection);
        })->getValues());

        $output->setCapabilities([
            'canEdit' => $this->isGranted(CollectionVoter::EDIT, $object),
            'canDelete' => $this->isGranted(CollectionVoter::DELETE, $object),
        ]);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AssetOutput::class === $to && $data instanceof Asset;
    }
}
