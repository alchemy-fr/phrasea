<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\ResolveEntitiesInput;
use App\Api\Model\Output\ResolveEntitiesOutput;
use App\Security\Voter\AbstractVoter;
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

        // Users are only disclosed to authenticated users (as /users is)
        $canReadUsers = $this->isGranted(JwtUser::IS_AUTHENTICATED_FULLY);

        $users = [];
        foreach ($data->entities as $iri) {
            if ($canReadUsers && str_starts_with($iri, $userIri)) {
                $users[] = substr($iri, $userIriLength);
            }
        }

        $fetchedUsers = !empty($users) ? $this->userRepository->getUsersByIds($users) : [];

        $entities = [];
        foreach ($data->entities as $iri) {
            try {
                if (str_starts_with($iri, $userIri)) {
                    $entities[$iri] = $canReadUsers
                        ? $fetchedUsers[substr($iri, $userIriLength)] ?? null
                        : ['notAllowed' => true];
                } else {
                    $entity = $this->iriConverter->getResourceFromIri($iri);
                    if ($this->isGranted(AbstractVoter::READ, $entity)) {
                        $entities[$iri] = $entity;
                    } else {
                        $entities[$iri] = [
                            'notAllowed' => true,
                        ];
                    }
                }
            } catch (ItemNotFoundException|ConversionException) {
                $entities[$iri] = null;
            }
        }

        return new ResolveEntitiesOutput($entities);
    }
}
