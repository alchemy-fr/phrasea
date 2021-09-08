<?php

declare(strict_types=1);

namespace App\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use App\Consumer\Handler\AssetConsumerNotifyHandler;
use App\Entity\BulkData;
use App\Entity\Commit;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
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

    public function __construct(EventProducer $eventProducer, FailedEventManager $failedEventManager)
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

    protected function triggerAgainAction()
    {
        $id = $this->request->query->get('id');
        /** @var Commit $commit */
        $commit = $this->em->getRepository(Commit::class)->find($id);

        if ($commit->isAcknowledged()) {
            $this->addFlash('danger', 'Commit has been acknowledged');

            return $this->redirectToRoute('easyadmin', [
                'action' => 'list',
                'entity' => $this->request->query->get('entity'),
            ]);
        }

        $this->eventProducer->publish(new EventMessage(AssetConsumerNotifyHandler::EVENT, [
            'id' => $commit->getId(),
        ]));

        return $this->redirectToRoute('easyadmin', [
            'action' => 'list',
            'entity' => $this->request->query->get('entity'),
        ]);
    }

    protected function renderBulkDataTemplate($actionName, $templatePath, array $parameters = [])
    {
        if ('new' === $actionName && 'BulkData' === $this->entity['name']) {
            $bulkData = $this->em->getRepository(BulkData::class)->findOneBy([]);
            if (null !== $bulkData) {
                $this->addFlash('danger', 'There could be only one bulk data set.');

                return $this->redirectToRoute('easyadmin', [
                    'action' => 'list',
                    'entity' => $this->request->query->get('entity'),
                ]);
            }
        }

        return $this->renderTemplate($actionName, $templatePath, $parameters);
    }
}
