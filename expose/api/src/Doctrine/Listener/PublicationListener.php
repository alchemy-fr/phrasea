<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Publication;
use App\Entity\PublicationProfile;
use App\Security\Voter\PublicationProfileVoter;
use App\Security\Voter\PublicationVoter;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PublicationListener implements EventSubscriber
{
    public function __construct(private readonly Security $security)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Publication) {
                $changeSet = $uow->getEntityChangeSet($entity);

                if (isset($changeSet['profile'][1]) && $changeSet['profile'][1] instanceof PublicationProfile) {
                    $profile = $changeSet['profile'][1];

                    if (!$this->security->isGranted(PublicationVoter::OPERATOR, $entity)) {
                        throw new AccessDeniedHttpException(sprintf('Not allowed to change profile on publication "%s"', $entity->getId()));
                    }
                    if (!$this->security->isGranted(PublicationProfileVoter::READ, $profile)) {
                        throw new AccessDeniedHttpException(sprintf('Not allowed assign profile "%s" on publication "%s"', $profile->getId(), $entity->getId()));
                    }
                }
            }
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::onFlush,
        ];
    }
}
