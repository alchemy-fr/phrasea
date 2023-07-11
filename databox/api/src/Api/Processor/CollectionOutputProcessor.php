<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\CollectionOutput;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Collection;
use App\Security\Voter\CollectionVoter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

class CollectionOutputProcessor extends AbstractSecurityProcessor
{
    public function __construct(private readonly CollectionSearch $collectionSearch)
    {
    }

    /**
     * @param Collection $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $output = new CollectionOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->setTitle($data->getTitle());
        $output->setPrivacy($data->getPrivacy());
        $output->setWorkspace($data->getWorkspace());

        if (in_array('collection:include_children', $context['groups'], true)) {
            $maxChildrenLimit = 30;
            if (preg_match('#(?:&|\?)childrenLimit=(\d+)#', (string) $context['request_uri'], $regs)) {
                $childrenLimit = $regs[1];
            } else {
                $childrenLimit = $maxChildrenLimit;
            }
            if ($childrenLimit > $maxChildrenLimit) {
                $childrenLimit = $maxChildrenLimit;
            }

            $key = sprintf(AbstractObjectNormalizer::DEPTH_KEY_PATTERN, $output::class, 'children');
            $maxDepth = (in_array('collection:2_level_children', $context['groups'], true)) ? 2 : 1;
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
            'canEdit' => $this->isGranted(CollectionVoter::EDIT, $data),
            'canDelete' => $this->isGranted(CollectionVoter::DELETE, $data),
            'canEditPermissions' => $this->isGranted(CollectionVoter::EDIT_PERMISSIONS, $data),
        ]);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return CollectionOutput::class === $to && $data instanceof Collection;
    }
}
