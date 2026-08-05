<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\Search\AttributeEntityListClear;
use App\Entity\Core\EntityList;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ClearEntitiesProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): EntityList
    {
        $list = DoctrineUtil::findStrict($this->em, EntityList::class, $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);

        $this->bus->dispatch(new AttributeEntityListClear($list->getId(), $list->getWorkspace()->getId()));

        return $list;
    }
}
