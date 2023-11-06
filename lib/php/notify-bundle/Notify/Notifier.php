<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Notify;

use GuzzleHttp\Client;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class Notifier implements NotifierInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private readonly Client $client)
    {
    }

    public function sendEmail(string $email, string $template, string $locale, array $parameters = []): void
    {
        $this->logger->debug(sprintf('Send email to "%s" with template "%s"', $email, $template));

        $this->client->request('POST', '/send-email', [
            'json' => [
                'email' => $email,
                'template' => $template,
                'parameters' => $parameters,
                'locale' => $locale,
            ],
        ]);
    }

    public function notifyTopic(string $topic, string $template, array $parameters = []): void
    {
        $this->logger->debug(sprintf('Send email topic "%s" with template "%s"', $topic, $template));

        $this->client->request('POST', '/notify-topic/'.$topic, [
            'json' => [
                'template' => $template,
                'parameters' => $parameters,
            ],
        ]);
    }

    public function notifyUser(
        string $userId,
        string $template,
        array $parameters = [],
        array $contactInfo = null
    ): void {
        $data = [
            'user_id' => $userId,
            'template' => $template,
            'parameters' => $parameters,
        ];
        if (null !== $contactInfo) {
            $data['contact_info'] = $contactInfo;
        }

        $this->logger->debug(sprintf('Notify user "%s" with template "%s"', $userId, $template));

        $this->client->request('POST', '/notify-user', [
            'json' => $data,
        ]);
    }

    public function registerUser(string $userId, array $contactInfo): void
    {
        $data = [
            'user_id' => $userId,
            'contact_info' => $contactInfo,
        ];

        $this->logger->debug(sprintf('Register user "%s" to notifier', $userId));

        $this->client->request('POST', '/register-user', [
            'json' => $data,
        ]);
    }

    public function deleteUser(string $userId): void
    {
        $data = [
            'user_id' => $userId,
        ];

        $this->logger->debug(sprintf('Delete user "%s" on notifier', $userId));

        $this->client->request('POST', '/delete-user', [
            'json' => $data,
        ]);
    }
}
