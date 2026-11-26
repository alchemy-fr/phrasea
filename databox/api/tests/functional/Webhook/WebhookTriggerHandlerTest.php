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
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WebhookTriggerHandlerTest extends AbstractDataboxTestCase
{
    public function testAnErrorOfTheReceiverIsLogged(): void
    {
        self::bootKernel();
        $em = self::getEntityManager();
        // A public address: MockHttpClient never connects to it
        $webhook = $this->createWebhook('https://1.2.3.4/hook');

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

    public function testAWebhookCannotReachThePrivateNetwork(): void
    {
        self::bootKernel();
        $em = self::getEntityManager();
        $webhook = $this->createWebhook('https://10.0.0.1/hook');

        $requests = 0;
        $client = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse('internal secret', ['http_code' => 500]);
        });
        $handler = new WebhookTriggerHandler(new WebhookApiClientFactory($client), $em);
        try {
            $handler(new WebhookTriggerMessage($webhook->getId(), 'asset:update', ['id' => 'a']));
            $this->fail('The request to the private network must be refused');
        } catch (TransportException) {
        }

        $this->assertSame(0, $requests);
        $this->assertCount(0, $em->getRepository(WebhookLog::class)->findBy(['webhook' => $webhook]));
    }

    public function testAnAllowedPrivateOriginIsReachedWithoutLoggingItsAnswer(): void
    {
        self::bootKernel();
        $em = self::getEntityManager();
        $webhook = $this->createWebhook('https://10.0.0.1/hook');

        $requests = 0;
        $client = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse('internal secret', [
                'http_code' => 500,
                'response_headers' => ['x-internal: yes'],
            ]);
        });
        $handler = new WebhookTriggerHandler(new WebhookApiClientFactory($client, 'https://10.0.0.1'), $em);
        $handler(new WebhookTriggerMessage($webhook->getId(), 'asset:update', ['id' => 'a']));

        $this->assertSame(1, $requests);
        $logs = $em->getRepository(WebhookLog::class)->findBy(['webhook' => $webhook]);
        $this->assertCount(1, $logs);
        $this->assertSame('HTTP 500', $logs[0]->getResponse());
    }

    public function testAnAllowedPrivateOriginDoesNotOpenTheOthers(): void
    {
        $factory = new WebhookApiClientFactory(new MockHttpClient(), 'https://hooks.internal, http://10.0.0.2:8080');

        $this->assertTrue($factory->isPrivateOriginAllowed('https://HOOKS.internal:443/x'));
        $this->assertTrue($factory->isPrivateOriginAllowed('http://10.0.0.2:8080/x'));
        $this->assertFalse($factory->isPrivateOriginAllowed('http://hooks.internal/x'));
        $this->assertFalse($factory->isPrivateOriginAllowed('https://hooks.internal@10.0.0.1/x'));
        $this->assertFalse($factory->isPrivateOriginAllowed('http://10.0.0.2/x'));
    }

    private function createWebhook(string $url): Webhook
    {
        $em = self::getEntityManager();
        EntityListener::$enabled = false;
        try {
            $webhook = new Webhook();
            $webhook->setUrl($url);
            $webhook->setEvents(['asset:update']);
            $em->persist($webhook);
            $em->flush();
        } finally {
            EntityListener::$enabled = true;
        }

        return $webhook;
    }
}
