<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\ResolveEntitiesInput;
use App\Api\Model\Output\ResolveEntitiesOutput;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;

class ResolveEntitiesProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IriConverterInterface $iriConverter,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @param ResolveEntitiesInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ResolveEntitiesOutput
    {
        $userIri = '/users/';
        $userIriLength = strlen($userIri);

        $users = [];
        foreach ($data->entities as $iri) {
            if (str_starts_with($iri, $userIri)) {
                $users[] = substr($iri, $userIriLength);
            }
        }

        if (!empty($users)) {
            $fetchedUsers = $this->userRepository->getUsersByIds($users);
        }

        $entities = [];
        foreach ($data->entities as $iri) {
            try {
                if (str_starts_with($iri, $userIri)) {
                    $entities[$iri] = $fetchedUsers[substr($iri, $userIriLength)] ?? null;
                } else {
                    $entities[$iri] = $this->iriConverter->getResourceFromIri($iri);
                }
            } catch (ItemNotFoundException|ConversionException) {
                $entities[$iri] = null;
            }
        }

        return new ResolveEntitiesOutput($entities);
    }
}
