<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final readonly class PublicationProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $itemsProvider,
        private EntityManagerInterface $em,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (Publication::GET_PUBLICATION_ROUTE_NAME !== $operation->getName()) {
            return $this->itemsProvider->provide($operation, $uriVariables, $context);
        }

        $slugOrId = $uriVariables['id'];
        $params = Uuid::isValid((string) $slugOrId) ? ['id' => $slugOrId] : ['slug' => $slugOrId];

        return $this->em
            ->getRepository(Publication::class)
            ->findOneBy($params);
    }
}
