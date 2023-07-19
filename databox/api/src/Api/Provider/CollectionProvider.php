<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\CollectionOutput;
use App\Api\Traits\SecurityAwareTrait;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Collection;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

class CollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly CollectionSearch $search,
        private readonly CollectionSearch $collectionSearch,
    ) {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): array|object {
        $filters = $context['filters'] ?? [];

        if ($filters['groupByWorkspace'] ?? false) {
            $results = $this->search->searchAggregationsByWorkspace($context['userId'], $context['groupIds'], $filters);

            return array_map(fn(Collection $collection): CollectionOutput => $this->transform($collection, $context), $results);
        }

        $result = $this->search->search($context['userId'], $context['groupIds'], $filters);

        return new PagerFantaApiPlatformPaginator(
            $result,
            fn(Collection $collection): CollectionOutput => $this->transform($collection, $context)
        );
    }

    private function transform(Collection $data, array $context = []): CollectionOutput
    {
        $output = new CollectionOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->setTitle($data->getTitle());
        $output->setPrivacy($data->getPrivacy());
        $output->setWorkspace($data->getWorkspace());

        if (in_array(Collection::GROUP_CHILDREN, $context['groups'], true)) {
            $maxChildrenLimit = 30;
            if (preg_match('#[&?]childrenLimit=(\d+)#', (string)$context['request_uri'], $regs)) {
                $childrenLimit = $regs[1];
            } else {
                $childrenLimit = $maxChildrenLimit;
            }
            if ($childrenLimit > $maxChildrenLimit) {
                $childrenLimit = $maxChildrenLimit;
            }

            $key = sprintf(AbstractObjectNormalizer::DEPTH_KEY_PATTERN, $output::class, 'children');
            $maxDepth = (in_array(Collection::GROUP_2LEVEL_CHILDREN, $context['groups'], true)) ? 2 : 1;
            $depth = $context[$key] ?? 0;
            if ($depth < $maxDepth) {
                if (false !== $data->getHasChildren()) {
                    $collections = $this->collectionSearch->search($context['userId'], $context['groupIds'], [
                        'parent' => $data->getId(),
                        'limit' => $childrenLimit,
                    ]);

                    $output->setChildren($collections);
                } else {
                    $output->setChildren([]);
                }
            }
        }

        $output->setCapabilities([
            'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
            'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
            'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
        ]);

        return $output;
    }
}
