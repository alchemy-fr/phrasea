<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\ItemProviderAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractCollectionProvider implements ProviderInterface
{
    use ItemProviderAwareTrait;

    protected EntityManagerInterface $em;

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return $this->itemProvider->provide($operation, $uriVariables, $context);
        }

        return $this->provideCollection($operation, $uriVariables, $context);
    }

    abstract protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object;

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

}
