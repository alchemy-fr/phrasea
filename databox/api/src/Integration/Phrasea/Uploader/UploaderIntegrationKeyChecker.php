<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Uploader;

use App\Integration\IntegrationConfig;
use App\Integration\IntegrationManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Authenticates the requests of the Uploader by the security key of an Uploader integration
 * (`Authorization: ApiKey <key>`): no user is behind them.
 */
final readonly class UploaderIntegrationKeyChecker
{
    public function __construct(
        private IntegrationManager $integrationManager,
    ) {
    }

    /**
     * @throws UnauthorizedHttpException without key
     * @throws NotFoundHttpException     when the integration is unknown, disabled or not an Uploader one
     * @throws AccessDeniedHttpException when the key is not the security key of the integration
     */
    public function authenticate(string $integrationId, Request $request): IntegrationConfig
    {
        $authToken = preg_replace('#^ApiKey\s+#', '', (string) $request->headers->get('Authorization', ''));
        if (!$authToken) {
            throw new UnauthorizedHttpException('ApiKey', 'Missing ApiKey token');
        }

        return $this->checkKey($integrationId, $authToken);
    }

    /**
     * @throws NotFoundHttpException     when the integration is unknown, disabled or not an Uploader one
     * @throws AccessDeniedHttpException when the key is not the security key of the integration
     */
    public function checkKey(string $integrationId, string $key): IntegrationConfig
    {
        if (!Uuid::isValid($integrationId)) {
            throw new NotFoundHttpException('Integration not found');
        }
        try {
            $integration = $this->integrationManager->loadIntegration($integrationId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Integration not found');
        }
        if (UploaderIntegration::NAME !== $integration->getIntegration() || !$integration->isEnabled()) {
            throw new NotFoundHttpException('Integration not found');
        }

        $config = $this->integrationManager->getIntegrationConfiguration($integration);
        if (!$config->matches('securityKey', $key)) {
            throw new AccessDeniedHttpException('Invalid token');
        }

        return $config;
    }
}
