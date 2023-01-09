<?php

declare(strict_types=1);

namespace App\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use Arthem\Bundle\RabbitBundle\Controller\AdminReplayControllerTrait;
use Arthem\Bundle\RabbitBundle\Model\FailedEventManager;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;

class AdminController extends EasyAdminController
{
    use PermissionTrait;
    use AdminReplayControllerTrait;

    private EventProducer $eventProducer;
    private FailedEventManager $failedEventManager;

    public function __construct(
        EventProducer      $eventProducer,
        FailedEventManager $failedEventManager
    )
    {
        $this->eventProducer = $eventProducer;
        $this->failedEventManager = $failedEventManager;
    }

    protected function getFailedEventManager(): FailedEventManager
    {
        return $this->failedEventManager;
    }

    protected function getEventProducer(): EventProducer
    {
        return $this->eventProducer;
    }
}
