<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Alchemy\WebhookBundle\Entity\Webhook;
use Alchemy\WebhookBundle\Entity\WebhookLog;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class WebhookTriggerHandler
{
    public function __construct(
        private Client $client,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(WebhookTriggerMessage $message): void
    {
        $id = $message->getWebhookId();
        $event = $message->getEvent();
        $payload = $message->getPayload();

        $webhook = $this->em->find(Webhook::class, $id);
        if (!$webhook instanceof Webhook) {
            throw new \InvalidArgumentException(sprintf('%s %s not found', Webhook::class, $id));
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

            $this->em->persist($log);
            $this->em->flush();
        }
    }
}
