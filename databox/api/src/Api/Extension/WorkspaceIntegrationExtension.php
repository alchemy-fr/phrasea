<?php

declare(strict_types=1);

namespace App\Api\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\FileActionsIntegrationInterface;
use App\Integration\IntegrationRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class WorkspaceIntegrationExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private IntegrationRegistry $integrationRegistry, private EntityManagerInterface $em)
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
        if (!isset($filters['workspace']) && !isset($filters['fileId'])) {
            throw new BadRequestHttpException('Missing "workspace" or "fileId" parameter');
        }

        if (isset($filters['fileId'])) {
            $file = $this->em->find(File::class, $filters['fileId']);
            if (!$file instanceof File) {
                throw new \InvalidArgumentException(sprintf('File "%s" not found', $filters['fileId']));
            }
            $filters['workspace'] = $file->getWorkspaceId();

            $supportedIntegrations = array_map(
                fn (FileActionsIntegrationInterface $integration): string => $integration::getName(),
                $this->integrationRegistry->getIntegrationsOfType(FileActionsIntegrationInterface::class)
            );

            $queryBuilder
                ->andWhere(sprintf('%s.integration IN (:integrations)', $rootAlias))
                ->setParameter('integrations', $supportedIntegrations)
            ;
        }

        $queryBuilder->andWhere(sprintf('%s.enabled = true', $rootAlias));
    }
}
