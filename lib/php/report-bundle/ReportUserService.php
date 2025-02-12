<?php

declare(strict_types=1);

namespace Alchemy\ReportBundle;

use Alchemy\ReportSDK\ReportClient;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

readonly class ReportUserService
{
    private bool $enabled;

    public function __construct(private ReportClient $client, private Security $security, string $reportBaseUrl)
    {
        $this->enabled = !empty($reportBaseUrl);
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
        if (!$this->enabled) {
            return;
        }

        $this->client->pushLog($action, $this->getUserId(), $itemId, $payload);
    }

    private function getUserId(): ?string
    {
        $token = $this->security->getToken();

        return match (true) {
            $token instanceof TokenInterface => $token->getUser()?->getId(),
            default => null,
        };
    }
}
