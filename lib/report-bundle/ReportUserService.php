<?php

declare(strict_types=1);

namespace Alchemy\ReportBundle;

use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use Alchemy\ReportSDK\ReportClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

class ReportUserService implements ReportUserServiceInterface
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

    public function pushHttpRequestLog(Request $request, string $action, ?string $itemId = null, array $payload = []): void
    {
        $payload['ip'] = $request->getClientIp();
        $payload['user_agent'] = $request->headers->get('User-Agent');
        $payload['accept_language'] = $request->headers->get('Accept-Language');

        $this->pushLog($action, $itemId, $payload);
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
