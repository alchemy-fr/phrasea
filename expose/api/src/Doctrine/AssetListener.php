<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Consumer\Handler\DeleteAssetHandler;
use App\Entity\Asset;
use App\Entity\SubDefinition;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;

class AssetListener implements EventSubscriber
{
    private EventProducer $eventProducer;
    /**
     * @var EventMessage[]
     */
    private array $eventStack = [];
    private array $positionCache = [];

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Asset) {
            if (0 === $entity->getPosition()) {
                $em = $args->getObjectManager();

                $pos = $this->getNextAssetPosition(
                    $em,
                    $entity->getPublication()->getId()
                );
                $entity->setPosition($pos);
            }
        }
    }

    /**
     * @param EntityManagerInterface $em
     */
    private function getNextAssetPosition(ObjectManager $em, string $publicationId): int
    {
        if (isset($this->positionCache[$publicationId])) {
            return ++$this->positionCache[$publicationId];
        }

        $position = $em->createQueryBuilder()
            ->select('MAX(a.position)')
            ->from(Asset::class, 'a')
            ->andWhere('a.publication = :p')
            ->setParameter('p', $publicationId)
            ->getQuery()
            ->getSingleScalarResult();

        $this->positionCache[$publicationId] = $position ?? 0;

        return ++$this->positionCache[$publicationId];
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Asset || $entity instanceof SubDefinition) {
                $this->eventStack[] = new EventMessage(DeleteAssetHandler::EVENT, [
                    'path' => $entity->getPath(),
                ]);
            }
        }
    }

    public function postFlush()
    {
        while ($message = array_shift($this->eventStack)) {
            $this->eventProducer->publish($message);
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::onFlush,
            Events::postFlush,
            Events::prePersist,
        ];
    }
}
