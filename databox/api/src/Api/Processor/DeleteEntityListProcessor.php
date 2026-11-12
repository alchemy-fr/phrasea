<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\Search\EntityListIndexCleanup;
use App\Entity\Core\EntityList;
use App\Service\Asset\Attribute\AttributeEntity\EntityListClearer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Deletes a list whatever its size: its entities and their attribute values are deleted in bulk, rather than one by
 * one through the cascades of the ORM, and the indexes are cleaned by the worker.
 */
final readonly class DeleteEntityListProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private EntityListClearer $clearer,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @param EntityList $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $listId = $data->getId();
        $workspaceId = $data->getWorkspace()->getId();
        // Read before the definitions bound to the list are deleted with it
        $definitions = $this->clearer->getIndexedDefinitions($listId, $workspaceId);

        $this->em->wrapInTransaction(function () use ($data, $listId, $workspaceId): void {
            $this->clearer->deleteRows($listId, $workspaceId);
            // The cascades find no entity nor value left
            $this->em->remove($data);
            $this->em->flush();
        });

        $this->bus->dispatch(new EntityListIndexCleanup($listId, $workspaceId, $definitions));

        return null;
    }
}
