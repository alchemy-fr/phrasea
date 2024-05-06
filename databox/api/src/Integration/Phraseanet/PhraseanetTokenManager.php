<?php

namespace App\Integration\Phraseanet;

use App\Security\JWTTokenManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class PhraseanetTokenManager
{
    public function __construct(
        private JWTTokenManager $JWTTokenManager,
    ) {
    }

    public function createToken(string $assetId, string $workflowId): string
    {
        return $this->JWTTokenManager->createToken($assetId, 3600 * 24 * 90, [
            'wid' => $workflowId,
        ]);
    }

    public function validateToken(string $assetId, string $token): string
    {
        try {
            $jwtToken = $this->JWTTokenManager->validateToken($assetId, $token);
        } catch (\InvalidArgumentException $e) {
            throw new AccessDeniedHttpException('Invalid token', $e);
        }

        $workflowId = $jwtToken->claims()->get('wid');
        if (empty($workflowId)) {
            throw new \InvalidArgumentException('Missing claim "wid"');
        }

        return $workflowId;
    }
}
