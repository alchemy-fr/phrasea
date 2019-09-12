<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Notify;

use GuzzleHttp\Client;

class Notifier
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function sendEmail(string $email, string $template, array $parameters = []): void
    {
        $this->client->request('GET', '/send-email', [
            'json' => [
                'email' => $email,
                'template' => $template,
                'parameters' => $parameters,
            ],
        ]);
    }

    public function notifyUser(
        string $userId, string
        $template,
        array $parameters = [],
        array $contactInfo = null
    ): void
    {
        $data = [
            'user_id' => $userId,
            'template' => $template,
            'parameters' => $parameters,
        ];
        if (null !== $contactInfo) {
            $data['contact_info'] = $contactInfo;
        }

        $this->client->request('GET', '/notify-user', [
            'json' => $data,
        ]);
    }
}
