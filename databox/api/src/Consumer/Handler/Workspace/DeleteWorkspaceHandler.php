<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Workspace;

use App\Elasticsearch\DeleteManager;
use App\Elasticsearch\Listener\DeferredIndexListener;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class DeleteWorkspaceHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'delete_workspace';

    private DeleteManager $deleteManager;

    public function __construct(DeleteManager $deleteManager)
    {
        $this->deleteManager = $deleteManager;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();
        $workspace = $em->find(Workspace::class, $id);
        if (!$workspace instanceof Workspace) {
            throw new ObjectNotFoundForHandlerException(Workspace::class, $id, __CLASS__);
        }

        $this->deleteManager->deleteWorkspace($id);

        DeferredIndexListener::disable();
        $em->beginTransaction();
        $em->getConnection()->getConfiguration()->setSQLLogger(null);
        try {
            $collections = $em->getRepository(Collection::class)
                ->createQueryBuilder('t')
                ->select('t.id')
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $id)
                ->getQuery()
                ->toIterable();

            foreach ($collections as $c) {
                $assets = $em->getRepository(Asset::class)
                    ->createQueryBuilder('t')
                    ->select('t.id')
                    ->andWhere('t.referenceCollection = :c')
                    ->setParameter('c', $c['id'])
                    ->getQuery()
                    ->toIterable();
                foreach ($assets as $a) {
                    $asset = $em->find(Asset::class, $a['id']);
                    $em->remove($asset);
                    $em->flush();
                    $em->clear();
                }

                $collection = $em->find(Collection::class, $c['id']);
                if ($collection instanceof Collection) {
                    $em->remove($collection);
                    $em->flush();
                }
                $em->clear();
            }

            $workspace = $em->find(Workspace::class, $id);
            $em->remove($workspace);
            $em->flush();
            $em->commit();
        } catch (\Throwable $e) {
            $em->rollback();
            throw $e;
        } finally {
            DeferredIndexListener::enable();
        }
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
