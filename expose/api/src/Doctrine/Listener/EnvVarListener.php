<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\EnvVar;
use App\Http\Cache\ProxyCachePurger;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class EnvVarListener implements EventSubscriber
{
    private ProxyCachePurger $proxyCachePurger;

    public function __construct(ProxyCachePurger $proxyCachePurger)
    {
        $this->proxyCachePurger = $proxyCachePurger;
    }

    private function handle(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof EnvVar) {
            $this->proxyCachePurger->purgeRoute('global_config');
        }
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
       $this->handle($args);
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->handle($args);
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->handle($args);
    }

    public function getSubscribedEvents()
    {
        return  [
            Events::postUpdate,
            Events::postPersist,
            Events::postRemove,
        ];
    }
}
