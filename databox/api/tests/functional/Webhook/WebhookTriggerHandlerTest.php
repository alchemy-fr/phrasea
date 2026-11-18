<?php

declare(strict_types=1);

namespace App\Tests\Functional\Webhook;

use Alchemy\WebhookBundle\Consumer\WebhookTriggerHandler;
use Alchemy\WebhookBundle\Consumer\WebhookTriggerMessage;
use Alchemy\WebhookBundle\Doctrine\Listener\EntityListener;
use Alchemy\WebhookBundle\Entity\Webhook;
use Alchemy\WebhookBundle\Entity\WebhookLog;
use Alchemy\WebhookBundle\Webhook\WebhookApiClientFactory;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WebhookTriggerHandlerTest extends AbstractDataboxTestCase
{
    public function testAnErrorOfTheReceiverIsLogged(): void
    {
        self::bootKernel();
        $em = self::getEntityManager();
        EntityListener::$enabled = false;
        try {
            $webhook = new Webhook();
            $webhook->setUrl('https://webhook.test/hook');
            $webhook->setEvents(['asset:update']);
            $em->persist($webhook);
            $em->flush();
        } finally {
            EntityListener::$enabled = true;
        }

        $client = new MockHttpClient(new MockResponse('receiver failure', [
            'http_code' => 500,
            'response_headers' => ['x-receiver: n8n'],
        ]));
        $handler = new WebhookTriggerHandler(new WebhookApiClientFactory($client), $em);
        $handler(new WebhookTriggerMessage($webhook->getId(), 'asset:update', ['id' => 'a']));

        $logs = $em->getRepository(WebhookLog::class)->findBy(['webhook' => $webhook]);
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('x-receiver: n8n', $logs[0]->getResponse());
        $this->assertStringContainsString('receiver failure', $logs[0]->getResponse());
    }
}
