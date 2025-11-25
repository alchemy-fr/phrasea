<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationContext;
use App\Integration\IntegrationInterface;
use App\Integration\IntegrationRegistry;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WorkspaceIntegrationCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    public function __construct(
        private IntegrationRegistry $integrationRegistry,
    ) {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $filters = $context['filters'] ?? [];

        $queryBuilder = $this->em->getRepository(WorkspaceIntegration::class)
            ->createQueryBuilder('t')
        ;

        if (null !== ($filters['enabled'] ?? null)) {
            $queryBuilder
                ->andWhere('t.enabled = :enabled')
                ->setParameter('enabled', $filters['enabled']);
        }

        $context = $filters['context'] ?? null;
        if (null !== $context) {
            $context = IntegrationContext::tryFrom($context) ?? throw new BadRequestHttpException(sprintf('Invalid context "%s"', $context));
            $supportedIntegrations = array_map(
                fn (IntegrationInterface $integration): string => $integration::getName(),
                $this->integrationRegistry->getSupportingIntegrations($context)
            );

            $queryBuilder
                ->andWhere('t.integration IN (:integrations)')
                ->setParameter('integrations', $supportedIntegrations)
            ;
        }

        if ($filters['workspace'] ?? false) {
            $workspaceId = str_replace('/workspaces/', '', $filters['workspace']);
            $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $workspaceId);

            $this->denyAccessUnlessGranted(AbstractVoter::READ, $workspace);

            $queryBuilder
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $workspace->getId());
        } else {
            $queryBuilder
                ->leftJoin('t.workspace', 'w');

            $user = $this->security->getUser();
            if ($user instanceof JwtUser) {
                AccessControlEntryRepository::joinAcl(
                    $queryBuilder,
                    $user->getId(),
                    $user->getGroups(),
                    Workspace::OBJECT_TYPE,
                    'w',
                    PermissionInterface::VIEW,
                    false
                );
                $queryBuilder->andWhere(sprintf('ace.id IS NOT NULL OR %1$s.ownerId = :uid OR %1$s.public = true OR t.workspace IS NULL', 'w'));
            } else {
                $queryBuilder->andWhere('t.workspace IS NULL');
            }
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
