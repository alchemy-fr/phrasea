<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Workspace;

use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class FlushWorkspaceHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'flush_workspace';

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();
        $workspace = $em->find(Workspace::class, $id);
        if (!$workspace instanceof Workspace) {
            throw new ObjectNotFoundForHandlerException(Workspace::class, $id, __CLASS__);
        }

        $collections = $em->getRepository(Collection::class)->findBy([
            'workspace' => $workspace->getId(),
        ]);

        foreach ($collections as $collection) {
            $em->remove($collection);
        }

        $em->flush();
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
