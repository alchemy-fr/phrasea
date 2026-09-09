<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\ResolveEntitiesInput;
use App\Api\Model\Output\ResolveEntitiesOutput;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

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
        $assetIri = '/assets/';
        $assetIriLength = strlen($assetIri);

        $isAssetIri = fn (string $iri): bool => str_starts_with($iri, $assetIri)
            && Uuid::isValid(substr($iri, $assetIriLength));

        $users = [];
        $assetIds = [];
        foreach ($data->entities as $iri) {
            if (str_starts_with($iri, $userIri)) {
                $users[] = substr($iri, $userIriLength);
            } elseif ($isAssetIri($iri)) {
                $assetIds[] = substr($iri, $assetIriLength);
            }
        }

        if (!empty($users)) {
            $fetchedUsers = $this->userRepository->getUsersByIds($users);
        }

        $fetchedAssets = [];
        if (!empty($assetIds)) {
            $fetchedAssets = DoctrineUtil::getIndexFromIds($this->em->getRepository(Asset::class), $assetIds);
        }

        $entities = [];
        foreach ($data->entities as $iri) {
            try {
                if (str_starts_with($iri, $userIri)) {
                    $entities[$iri] = $fetchedUsers[substr($iri, $userIriLength)] ?? null;
                } elseif ($isAssetIri($iri)) {
                    $entities[$iri] = $this->resolveReadableEntity($fetchedAssets[substr($iri, $assetIriLength)] ?? null);
                } else {
                    $entities[$iri] = $this->resolveReadableEntity($this->iriConverter->getResourceFromIri($iri));
                }
            } catch (ItemNotFoundException|ConversionException) {
                $entities[$iri] = null;
            }
        }

        return new ResolveEntitiesOutput($entities);
    }

    private function resolveReadableEntity(?object $entity): object|array|null
    {
        if (null === $entity) {
            return null;
        }

        if (!$this->isGranted(AbstractVoter::READ, $entity)) {
            return [
                'notAllowed' => true,
            ];
        }

        return $entity;
    }
}
