<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Output\CollectionOutput;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Collection;
use App\Security\Voter\CollectionVoter;
use Symfony\Component\Security\Core\Security;

class CollectionOutputDataTransformer implements DataTransformerInterface
{
    private CollectionSearch $collectionSearch;
    private Security $security;

    public function __construct(CollectionSearch $collectionSearch, Security $security)
    {
        $this->collectionSearch = $collectionSearch;
        $this->security = $security;
    }

    /**
     * @param Collection $object
     *
     * @return CollectionOutput
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new CollectionOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->setTitle($object->getTitle());

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
            'canEdit' => $this->security->isGranted(CollectionVoter::EDIT, $object),
            'canDelete' => $this->security->isGranted(CollectionVoter::DELETE, $object),
        ]);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return CollectionOutput::class === $to && $data instanceof Collection;
    }
}
