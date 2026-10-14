<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationContext;
use App\Integration\IntegrationInterface;
use App\Integration\IntegrationRegistry;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WorkspaceIntegrationCollectionProvider extends AbstractCollectionProvider
{
    use ParameterValuesTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly IntegrationRegistry $integrationRegistry,
    ) {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            return [];
        }

        $queryBuilder = $this->em->getRepository(WorkspaceIntegration::class)
            ->createQueryBuilder('t')
        ;

        if (null !== $enabled = self::getParameterValue($operation, 'enabled')) {
            $queryBuilder
                ->andWhere('t.enabled = :enabled')
                ->setParameter('enabled', (bool) $enabled);
        }

        $queryBuilder
            ->addOrderBy('t.createdAt', 'ASC');

        $workspace = null;
        if (null !== $workspaceId = self::getParameterId($operation, 'workspace')) {
            $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $workspaceId);
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $workspace);
        }

        if (!$this->isAdmin() && (
            null === $workspace || !$this->isGranted(AbstractVoter::EDIT, $workspace)
        )) {
            $queryBuilder->addGroupBy('t.id');
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $user->getId(),
                $user->getGroups(),
                WorkspaceIntegration::OBJECT_TYPE,
                't',
                PermissionInterface::VIEW,
                false,
                aceAlias: 'iace'
            );
            $queryBuilder->andWhere('iace.id IS NOT NULL OR t.public = true OR t.ownerId = :uid')
                ->setParameter('uid', $user->getId());
        }

        $integrationContext = self::getParameterValue($operation, 'context');
        if (\is_string($integrationContext) && '' !== $integrationContext) {
            $context = IntegrationContext::tryFrom($integrationContext) ?? throw new BadRequestHttpException(sprintf('Invalid context "%s"', $integrationContext));
            $supportedIntegrations = array_map(
                fn (IntegrationInterface $integration): string => $integration::getName(),
                $this->integrationRegistry->getSupportingIntegrations($context)
            );

            $queryBuilder
                ->andWhere('t.integration IN (:integrations)')
                ->setParameter('integrations', $supportedIntegrations)
            ;
        }

        if (null !== $workspace) {
            $queryBuilder
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $workspace->getId());
        } elseif (self::getParameterValue($operation, 'global', false)) {
            $queryBuilder->andWhere('t.workspace IS NULL');
        } else {
            $queryBuilder
                ->leftJoin('t.workspace', 'w');

            $user = $this->security->getUser();
            if ($user instanceof JwtUser) {
                $queryBuilder->addGroupBy('t.id');
                AccessControlEntryRepository::joinAcl(
                    $queryBuilder,
                    $user->getId(),
                    $user->getGroups(),
                    Workspace::OBJECT_TYPE,
                    'w',
                    PermissionInterface::VIEW,
                    false,
                    aceAlias: 'wace'
                );
                $queryBuilder->andWhere(sprintf('wace.id IS NOT NULL OR %1$s.ownerId = :uid OR %1$s.public = true OR t.workspace IS NULL', 'w'));
            } else {
                $queryBuilder->andWhere('t.workspace IS NULL');
            }
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
