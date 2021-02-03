<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Output\TagOutput;
use App\Api\Model\Output\WorkspaceOutput;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Security\Voter\CollectionVoter;
use App\Security\Voter\WorkspaceVoter;

class WorkspaceOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private CollectionSearch $collectionSearch;

    public function __construct(CollectionSearch $collectionSearch)
    {
        $this->collectionSearch = $collectionSearch;
    }

    /**
     * @param Workspace $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new WorkspaceOutput();
        $output->setId($object->getId());
        $output->setName($object->getName());

        $output->setCapabilities([
            'canEdit' => $this->isGranted(WorkspaceVoter::EDIT, $object),
            'canDelete' => $this->isGranted(WorkspaceVoter::DELETE, $object),
        ]);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return WorkspaceOutput::class === $to && $data instanceof Workspace;
    }
}
