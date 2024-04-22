<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Workspace;

use App\Entity\Core\RenditionRule;
use App\Entity\Core\TagFilterRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OnWorkspaceDeleteHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(OnWorkspaceDelete $message): void
    {
        $id = $message->getWorkspaceId();

        $this->em->getRepository(TagFilterRule::class)
            ->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.objectType = :type')
            ->andWhere('t.objectId = :id')
            ->setParameter('type', TagFilterRule::TYPE_WORKSPACE)
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();

        $this->em->getRepository(RenditionRule::class)
            ->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.objectType = :type')
            ->andWhere('t.objectId = :id')
            ->setParameter('type', TagFilterRule::TYPE_WORKSPACE)
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }
}
