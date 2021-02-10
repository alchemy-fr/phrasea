<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\CollectionOutput;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Collection;
use App\Security\Voter\CollectionVoter;

class CollectionOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private CollectionSearch $collectionSearch;

    public function __construct(CollectionSearch $collectionSearch)
    {
        $this->collectionSearch = $collectionSearch;
    }

    /**
     * @param Collection $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new CollectionOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->setTitle($object->getTitle());
        $output->setPrivacy($object->getPrivacy());
        $output->setWorkspace($object->getWorkspace());

        if (($context['depth'] ?? 0) < 1) {
            $output->setChildren(array_map(function (Collection $child) use ($context): CollectionOutput {
                return $this->transform($child, CollectionOutput::class, array_merge($context, [
                    'depth' => ($context['depth'] ?? 0) + 1,
                ]));
            }, $this->collectionSearch->search($context['userId'], $context['groupIds'], [
                'parent' => $object->getId(),
            ])));
        }

        $output->setCapabilities([
            'canEdit' => $this->isGranted(CollectionVoter::EDIT, $object),
            'canDelete' => $this->isGranted(CollectionVoter::DELETE, $object),
            'canEditPermissions' => $this->isGranted(CollectionVoter::EDIT_PERMISSIONS, $object),
        ]);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return CollectionOutput::class === $to && $data instanceof Collection;
    }
}
