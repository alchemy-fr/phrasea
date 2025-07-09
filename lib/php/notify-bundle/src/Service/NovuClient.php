<?php

namespace Alchemy\NotifyBundle\Service;

use Alchemy\CoreBundle\Listener\ClientExceptionListener;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class NovuClient
{
    private HttpClientInterface $client;

    public function __construct(
        #[Autowire(param: 'alchemy_notify.novu.secret_key')]
        private string $secretKey,
        #[Autowire(service: 'novu.client')]
        HttpClientInterface $client,
        private ClientExceptionListener $clientExceptionListener,
        private LoggerInterface $logger,
    ) {
        $this->client = $client->withOptions([
            'headers' => [
                'Authorization' => 'ApiKey '.$this->secretKey,
            ],
        ]);
    }

    public function notifyTopic(
        string $topicKey,
        ?string $authorId,
        string $notificationId,
        array $parameters = [],
        array $options = [],
    ): void {
        $this->logger->info(sprintf('Sending notification to topic "%s"', $topicKey), [
            'notificationId' => $notificationId,
            'parameters' => $parameters,
            'authorId' => $authorId,
        ]);

        $data = [
            'name' => $notificationId,
            'to' => [
                'type' => 'Topic',
                'topicKey' => $topicKey,
            ],
            'payload' => $parameters,
        ];

        if (null !== $authorId) {
            $data['actor'] = ['subscriberId' => $authorId];
        }

        $transactionId = $options['transactionId'] ?? null;
        if (null !== $transactionId) {
            $data['transactionId'] = $transactionId;
        }

        $this->request('POST', '/v1/events/trigger', [
            'json' => $data,
        ]);
    }

    public function broadcast(
        string $notificationId,
        array $parameters = [],
        array $options = [],
    ): void {
        $data = [
            'name' => $notificationId,
            'payload' => empty($parameters) ? new \stdClass() : $parameters,
        ];

        $transactionId = $options['transactionId'] ?? null;
        if (null !== $transactionId) {
            $data['transactionId'] = $transactionId;
        }

        $this->request('POST', '/v1/events/trigger/broadcast', [
            'json' => $data,
        ]);
    }

    private function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->clientExceptionListener->wrapClientRequest(function () use ($method, $url, $options): ResponseInterface {
            $response = $this->client->request($method, $url, $options);
            $response->getHeaders(throw: true);

            return $response;
        });
    }

    public function addTopicSubscribers(string $topicKey, array $subscribers): void
    {
        $this->logger->info(sprintf('Add subscribers to topic "%s"', $topicKey), [
            'subscribers' => $subscribers,
        ]);

        $this->request('POST', sprintf('/v1/topics/%s/subscribers', $topicKey), [
            'json' => [
                'subscribers' => $subscribers,
            ],
        ]);
    }

    public function upsertSubscribers(array $subscribers): void
    {
        if (empty($subscribers)) {
            return;
        }

        if (count($subscribers) > 10) {
            foreach (array_chunk($subscribers, 500) as $chunk) {
                $this->request('POST', '/v1/subscribers/bulk', [
                    'json' => [
                        'subscribers' => $chunk,
                    ],
                ]);
            }
        } else {
            foreach ($subscribers as $subscriber) {
                $this->request('POST', '/v1/subscribers', [
                    'json' => $subscriber,
                ]);
            }
        }
    }

    public function createTopic(string $topicKey): void
    {
        $this->logger->info(sprintf('Creating topic "%s"', $topicKey));

        $this->request('POST', '/v1/topics', [
            'json' => [
                'key' => $topicKey,
                'name' => $topicKey,
            ],
        ]);
    }

    public function removeTopicSubscribers(string $topicKey, array $subscribers): void
    {
        $this->logger->info(sprintf('Removing subscribers from topic "%s"', $topicKey), [
            'subscribers' => $subscribers,
        ]);

        $this->request('POST', sprintf('/v1/topics/%s/subscribers/removal', $topicKey), [
            'json' => [
                'subscribers' => $subscribers,
            ],
        ]);
    }

    public function isSubscribed(string $topicKey, string $subscriberId): bool
    {
        try {
            $response = $this->request('GET', sprintf('/v1/topics/%s/subscribers/%s', $topicKey, $subscriberId));
        } catch (ClientExceptionInterface $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
                return false;
            }

            throw $e;
        }

        return 200 === $response->getStatusCode();
    }
}
