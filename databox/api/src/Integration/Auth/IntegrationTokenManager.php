<?php

namespace App\Integration\Auth;

use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

final readonly class IntegrationTokenManager
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function getAccessToken(IntegrationToken $integrationToken, \Closure $onRenew): string
    {
        if ($integrationToken->isExpired()) {
            $this->em->remove($integrationToken);
            $this->em->flush();

            throw new \InvalidArgumentException('Token was expired');
        }

        $tokens = $integrationToken->getToken();

        if (isset($tokens['refresh_token']) && $tokens['expires_at'] < time()) {
            try {
                $data = $onRenew($tokens['refresh_token'], $integrationToken);
            } catch (ClientExceptionInterface $e) {
                if (400 === $e->getCode()) {
                    $this->em->remove($integrationToken);
                    $this->em->flush();
                }

                throw $e;
            }

            $integrationToken = $this->refreshToken($integrationToken, $data);
        }

        return $integrationToken->getToken()['access_token'];
    }

    private function refreshToken(IntegrationToken $integrationToken, array $data): IntegrationToken
    {
        $this->normalizeResponse($integrationToken, $data);

        $this->em->persist($integrationToken);
        $this->em->flush();

        return $integrationToken;
    }

    private function normalizeResponse(IntegrationToken $integrationToken, array $data): void
    {
        $time = time();
        $data['expires_at'] = $time + $data['expires_in'];
        if (isset($data['refresh_expires_in'])) {
            $data['refresh_expires_at'] = $time + $data['refresh_expires_in'];
        }
        $integrationToken->setExpiresAt((new \DateTimeImmutable())->setTimestamp($data['refresh_expires_at'] ?? $data['expires_at']));
        $integrationToken->setToken($data);
    }

    public function persistToken(WorkspaceIntegration $workspaceIntegration, array $data, string $userId): IntegrationToken
    {
        $integrationToken = new IntegrationToken();
        $integrationToken->setIntegration($workspaceIntegration);
        $integrationToken->setUserId($userId);

        $this->normalizeResponse($integrationToken, $data);

        $this->em->persist($integrationToken);
        $this->em->flush();

        return $integrationToken;
    }
}
