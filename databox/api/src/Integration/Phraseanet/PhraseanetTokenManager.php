<?php

namespace App\Integration\Phraseanet;

use Alchemy\AuthBundle\Security\UriJwtManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class PhraseanetTokenManager
{
    public function __construct(
        private UriJwtManager $uriJwtManager,
    ) {
    }

    public function createToken(string $assetId, string $workflowId): string
    {
        return $this->uriJwtManager->createToken($assetId, 3600 * 24 * 90, [
            'wid' => $workflowId,
        ]);
    }

    public function validateToken(string $assetId, string $token): string
    {
        try {
            $jwtToken = $this->uriJwtManager->validateJWT($assetId, $token);
        } catch (\InvalidArgumentException|AccessDeniedHttpException $e) {
            throw new AccessDeniedHttpException('Invalid token', $e);
        }

        $workflowId = $jwtToken->claims()->get('wid');
        if (empty($workflowId)) {
            throw new \InvalidArgumentException('Missing claim "wid"');
        }

        return $workflowId;
    }
}
