<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\CollectionOutput;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Collection;
use App\Security\Voter\CollectionVoter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

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

        if (in_array('collection:include_children', $context['groups'], true)) {
            $key = sprintf(AbstractObjectNormalizer::DEPTH_KEY_PATTERN, get_class($output), 'children');
            $maxDepth = (in_array('collection:2_level_children', $context['groups'], true)) ? 2 : 1;
            if (($context[$key] ?? 0) < $maxDepth) {
                $collections = $this->collectionSearch->search($context['userId'], $context['groupIds'], [
                    'parent' => $object->getId(),
                ]);

                $output->setChildren($collections);
            }
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
