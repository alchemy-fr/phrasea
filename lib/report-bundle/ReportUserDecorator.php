<?php

declare(strict_types=1);

namespace Alchemy\ReportBundle;

use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use Alchemy\ReportSDK\ReportClient;
use Symfony\Component\Security\Core\Security;

class ReportUserDecorator
{
    /**
     * @var ReportClient
     */
    private $client;

    /**
     * @var Security
     */
    private $security;

    public function __construct(ReportClient $client, Security $security)
    {
        $this->client = $client;
        $this->security = $security;
    }

    public function pushLog(string $action, ?string $itemId = null, array $payload = []): void
    {
        $this->client->pushLog($action, $this->getUserId(), $itemId, $payload);
    }

    private function getUserId(): ?string
    {
        $token = $this->security->getToken();

        switch (true) {
            case $token instanceof RemoteAuthToken:
                return $token->getUser()->getId();
        }

        return null;
    }
}
