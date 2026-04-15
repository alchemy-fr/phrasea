<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\RemoveFromProfileInput;
use App\Entity\Profile\Profile;
use App\Repository\Profile\ProfileRepository;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\Messenger\MessageBusInterface;

class RemoveFromProfileProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly ProfileRepository $repository,
        private readonly IriConverterInterface $iriConverter,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param RemoveFromProfileInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Profile
    {
        $id = $uriVariables['id'];
        $list = DoctrineUtil::findStrictByRepo($this->repository, $id);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);

        $this->repository->removeFromList($id, $data->items);

        return $list;
    }
}
