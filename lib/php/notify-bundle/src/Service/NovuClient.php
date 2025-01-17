<?php

namespace Alchemy\NotifyBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class NovuClient
{
    private HttpClientInterface $client;

    public function __construct(
        #[Autowire(param: 'alchemy_notify.novu.secret_key')]
        private string $secretKey,
        #[Autowire(service: 'novu.client')]
        HttpClientInterface $client,
    )
    {
        $this->client = $client->withOptions([
            'headers' => [
                'Authorization' => 'ApiKey ' . $this->secretKey,
            ],
        ]);
    }

    public function notifyTopic(
        string $topicKey,
        ?string $authorId,
        string $notificationId,
        array $parameters = [],
    ): void {
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

        $this->client->request('POST', '/v1/events/trigger', [
            'json' => $data,
        ]);
    }

    public function addTopicSubscribers(string $topicKey, array $subscribers): void
    {
        $this->client->request('POST', sprintf('/v1/topics/%s/subscribers', $topicKey), [
            'json' => [
                'subscribers' => $subscribers,
            ],
        ]);
    }

    public function removeTopicSubscribers(string $topicKey, array $subscribers): void
    {
        $this->client->request('POST', sprintf('/v1/topics/%s/subscribers/removal', $topicKey), [
            'json' => [
                'subscribers' => $subscribers,
            ],
        ]);
    }
}
