<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\ImportEntitiesInput;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class ImportEntitiesProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param ImportEntitiesInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): EntityList
    {
        $listId = $uriVariables['id'];
        $list = DoctrineUtil::findStrict($this->em, EntityList::class, $listId);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);

        foreach ($data->values ?? [] as $value) {
            $entity = new AttributeEntity();
            $entity->setList($list);
            $entity->setValue($value);
            $this->em->persist($entity);
        }

        $this->em->flush();

        return $list;
    }
}
