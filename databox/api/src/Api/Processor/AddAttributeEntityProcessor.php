<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\AttributeEntity;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class AddAttributeEntityProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param AttributeEntity $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AttributeEntity
    {
        $data->setCreatorId($this->getStrictUserOrOAuthClient()?->getUserIdentifier());

        if (!$this->isGranted(AbstractVoter::EDIT, $data->getList())) {
            $data->setStatus(AttributeEntity::STATUS_PENDING);
        }

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
