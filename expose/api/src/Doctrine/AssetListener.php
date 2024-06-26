<?php

declare(strict_types=1);

namespace App\Doctrine;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\DeleteAsset;
use App\Entity\Asset;
use App\Entity\SubDefinition;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;

#[AsDoctrineListener(Events::onFlush)]
#[AsDoctrineListener(Events::prePersist)]
class AssetListener implements EventSubscriber
{
    private array $positionCache = [];

    public function __construct(private readonly PostFlushStack $postFlushStack)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
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

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Asset || $entity instanceof SubDefinition) {
                $this->postFlushStack->addBusMessage(new DeleteAsset($entity->getPath()));
            }
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
            Events::prePersist,
        ];
    }
}
