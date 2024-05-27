<?php

declare(strict_types=1);

namespace App\Api\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\BasketActionsIntegrationInterface;
use App\Integration\FileActionsIntegrationInterface;
use App\Integration\IntegrationInterface;
use App\Integration\IntegrationRegistry;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class WorkspaceIntegrationExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private IntegrationRegistry $integrationRegistry)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass, $context);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, array $context): void
    {
        if (WorkspaceIntegration::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $filters = $context['filters'] ?? [];
        $type = $filters['type'] ?? throw new BadRequestHttpException('Missing integration type');

        $interface = [
            'basket' => BasketActionsIntegrationInterface::class,
            'file' => FileActionsIntegrationInterface::class,
        ][$type] ?? throw new BadRequestHttpException(sprintf('Unsupported integration type "%s"', $filters['type']));

        $supportedIntegrations = array_map(
            fn (IntegrationInterface $integration): string => $integration::getName(),
            $this->integrationRegistry->getIntegrationsOfType($interface)
        );

        $queryBuilder
            ->andWhere(sprintf('%s.integration IN (:integrations)', $rootAlias))
            ->setParameter('integrations', $supportedIntegrations)
        ;

        $queryBuilder->andWhere(sprintf('%s.enabled = true', $rootAlias));
    }
}
