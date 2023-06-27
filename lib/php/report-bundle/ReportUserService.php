<?php

declare(strict_types=1);

namespace Alchemy\ReportBundle;

use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use Alchemy\ReportSDK\ReportClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

class ReportUserService
{
    private readonly bool $enabled;

    public function __construct(private readonly ReportClient $client, private readonly Security $security, string $reportBaseUrl)
    {
        $this->enabled = !empty($reportBaseUrl);
    }

    public function pushHttpRequestLog(Request $request, string $action, string $itemId = null, array $payload = []): void
    {
        $payload['ip'] = $request->getClientIp();
        $payload['user_agent'] = $request->headers->get('User-Agent');
        $payload['accept_language'] = $request->headers->get('Accept-Language');

        $this->pushLog($action, $itemId, $payload);
    }

    public function pushLog(string $action, string $itemId = null, array $payload = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->client->pushLog($action, $this->getUserId(), $itemId, $payload);
    }

    private function getUserId(): ?string
    {
        $token = $this->security->getToken();

        return match (true) {
            $token instanceof RemoteAuthToken,
            $token instanceof PostAuthenticationGuardToken => $token->getUser()->getId(),
            default => null,
        };
    }
}
