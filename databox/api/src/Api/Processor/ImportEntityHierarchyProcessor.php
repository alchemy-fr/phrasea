<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\EntityHierarchyImportInput;
use App\Attribute\Hierarchy\EntityHierarchyImporter;
use App\Entity\Core\EntityList;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Answers the import report: {applied, rejected: [{line, entity, reason}], rootEntityId}.
 */
final class ImportEntityHierarchyProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EntityHierarchyImporter $importer,
    ) {
    }

    /**
     * @param EntityHierarchyImportInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $list = DoctrineUtil::findStrict($this->em, EntityList::class, (string) $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);

        return new JsonResponse($this->importer->import($list, (string) $data->data, $data->dryRun));
    }
}
