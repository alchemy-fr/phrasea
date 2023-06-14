<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Alchemy\WebhookBundle\Entity\Webhook;
use Alchemy\WebhookBundle\Entity\WebhookLog;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

class WebhookTriggerHandler extends AbstractEntityManagerHandler
{
    private const EVENT = 'webhook_trigger';
    public const TEST_EVENT = '_test';

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function handle(EventMessage $message): void
    {
        $p = $message->getPayload();
        $id = $p['id'];
        $event = $p['event'];
        $payload = $p['payload'];

        $em = $this->getEntityManager();
        $webhook = $em->find(Webhook::class, $id);
        if (!$webhook instanceof Webhook) {
            throw new ObjectNotFoundForHandlerException(Webhook::class, $id, __CLASS__);
        }

        if (!$webhook->isActive()) {
            return;
        }

        $uri = $webhook->getUrl();

        try {
            $this->client->post($uri, [
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::VERIFY => $webhook->isVerifySSL(),
                RequestOptions::TIMEOUT => $webhook->getTimeout(),
                'json' => [
                    'event' => $event,
                    'payload' => $payload,
                ],
            ]);
        } catch (RequestException $e) {
            $log = new WebhookLog();
            $response = $e->getResponse();

            if (null === $response) {
                $log->setResponse($e->getMessage());
            } else {
                $res = '';
                foreach (array_keys($response->getHeaders()) as $h) {
                    $res .= $h.': '.$response->getHeaderLine($h)."\n";
                }
                $res .= "\n\n";
                $res .= $response->getBody()->getContents();
                $log->setResponse($res);
            }

            $log->setEvent($event);
            $log->setWebhook($webhook);
            $log->setPayload($payload);

            $em->persist($log);
            $em->flush();
        }
    }

    public static function createEvent(string $webhookId, string $event, array $payload): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'id' => $webhookId,
            'event' => $event,
            'payload' => $payload,
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
