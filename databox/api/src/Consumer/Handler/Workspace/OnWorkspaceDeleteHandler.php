<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Workspace;

use App\Entity\Core\RenditionRule;
use App\Entity\Core\TagFilterRule;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class OnWorkspaceDeleteHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'on_workspace_delete';

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();

        $em->getRepository(TagFilterRule::class)
            ->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.objectType = :type')
            ->andWhere('t.objectId = :id')
            ->setParameter('type', TagFilterRule::TYPE_WORKSPACE)
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
        $em->getRepository(RenditionRule::class)
            ->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.objectType = :type')
            ->andWhere('t.objectId = :id')
            ->setParameter('type', TagFilterRule::TYPE_WORKSPACE)
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $id): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'id' => $id,
        ]);
    }
}
