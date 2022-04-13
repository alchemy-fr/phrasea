<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Controller;

use Alchemy\WebhookBundle\Consumer\WebhookTriggerHandler;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;

/**
 * @target EasyAdminController
 */
trait AdminControllerTrait
{
    private EventProducer $eventProducer;

    /**
     * @required
     */
    public function setEventProducer(EventProducer $eventProducer): void
    {
        $this->eventProducer = $eventProducer;
    }

    public function testWebhookAction()
    {
        $id = $this->request->query->get('id');
        $entity = $this->em->find($this->entity['class'], $id);

        $this->eventProducer->publish(WebhookTriggerHandler::createEvent($entity->getId(), WebhookTriggerHandler::TEST_EVENT, [
        ]));

        $this->addFlash('success', 'Webhook triggered');

        return $this->redirectToReferrer();
    }
}
