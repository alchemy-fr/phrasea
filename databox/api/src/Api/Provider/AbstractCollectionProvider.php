<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\ItemProviderAwareTrait;
use App\Elasticsearch\Exception\MissingSearchIndexException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractCollectionProvider implements ProviderInterface
{
    use ItemProviderAwareTrait;

    protected EntityManagerInterface $em;
    protected LoggerInterface $logger;

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return $this->itemProvider->provide($operation, $uriVariables, $context);
        }

        try {
            return $this->provideCollection($operation, $uriVariables, $context);
        } catch (MissingSearchIndexException $e) {
            // The index is not in place yet (fresh workspace, failed populate).
            // An empty collection keeps the client usable; the alert belongs in the logs.
            $this->logger->error($e->getMessage(), [
                'resource' => $operation->getClass(),
                'exception' => $e,
            ]);

            return [];
        }
    }

    abstract protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object;

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    #[Required]
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
