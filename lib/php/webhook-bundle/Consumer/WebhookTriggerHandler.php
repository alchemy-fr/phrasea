<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Doctrine\ORM\EntityManagerInterface;
use Alchemy\WebhookBundle\Entity\Webhook;
use Alchemy\WebhookBundle\Entity\WebhookLog;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

#[AsMessageHandler]
final readonly class WebhookTriggerHandler
{
    public function __construct(
        private HttpClientInterface $client,
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
            $this->client->request('POST', $uri, [
                'max_redirects' => 0,
                'verify_peer' => $webhook->isVerifySSL(),
                'timeout' => $webhook->getTimeout(),
                'json' => [
                    'event' => $event,
                    'payload' => $payload,
                ],
            ]);
        } catch (HttpExceptionInterface $e) {
            $log = new WebhookLog();
            $response = $e->getResponse();

            if (null === $response) {
                $log->setResponse($e->getMessage());
            } else {
                $res = '';
                foreach ($response->getHeaders() as $h => $v) {
                    $res .= $h.': '. implode(',', $v) ."\n";
                }
                $res .= "\n\n";
                $res .= $response->getContent();
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
