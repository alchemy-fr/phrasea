<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Entity\Core\Workspace;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use App\Service\Workspace\WorkspaceCreator;

/**
 * Fixtures of the integration tests: a workspace owned by OWNER that MEMBER
 * can see (VIEW only), and integrations, data and tokens created directly in
 * the database.
 */
trait IntegrationTestTrait
{
    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string MEMBER = KeycloakClientTestMock::OTHER_USER_UID;

    private const string SECRET_ACCESS_TOKEN = 'secret-access-token';
    private const string SECRET_REFRESH_TOKEN = 'secret-refresh-token';

    private const array EXPOSE_CONFIG = [
        'baseUrl' => 'https://api-expose.phrasea.test',
        'clientId' => 'expose-app',
        'clientUrl' => 'https://expose.phrasea.test',
    ];

    private function headers(?string $userId): array
    {
        return null === $userId ? [] : [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }

    private function createSharedWorkspace(): Workspace
    {
        $workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
        $this->addUserOnWorkspace(self::MEMBER, $workspace->getId());

        return $workspace;
    }

    /**
     * A workspace the test users cannot access.
     */
    private function createOtherWorkspace(): Workspace
    {
        $workspace = new Workspace();
        $workspace->setName('Other workspace');
        $workspace->setSlug('other-workspace');
        $workspace->setOwnerId('someone-else');
        $workspace->setEnabledLocales(['en']);
        self::getService(WorkspaceCreator::class)->createWorkspace($workspace);
        self::getEntityManager()->flush();

        return $workspace;
    }

    private function createIntegration(
        ?Workspace $workspace,
        string $type,
        array $config = [],
        bool $public = false,
        ?string $name = null,
        string $ownerId = self::OWNER,
        bool $enabled = true,
    ): WorkspaceIntegration {
        $em = self::getEntityManager();

        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setIntegration($type);
        $integration->setConfig($config);
        $integration->setPublic($public);
        $integration->setName($name);
        $integration->setOwnerId($ownerId);
        $integration->setEnabled($enabled);
        $em->persist($integration);
        $em->flush();

        return $integration;
    }

    private function createIntegrationData(
        WorkspaceIntegration $integration,
        ?string $userId,
        ?AbstractUuidEntity $object = null,
        string $name = 'note',
        string $value = 'some value',
    ): IntegrationData {
        $em = self::getEntityManager();

        $data = new IntegrationData();
        $data->setIntegration($integration);
        $data->setUserId($userId);
        $data->setObject($object);
        $data->setName($name);
        $data->setValue($value);
        $em->persist($data);
        $em->flush();

        return $data;
    }

    private function createIntegrationToken(WorkspaceIntegration $integration, ?string $userId, string $expiresAt = '+1 hour'): IntegrationToken
    {
        $em = self::getEntityManager();

        $token = new IntegrationToken();
        $token->setIntegration($integration);
        $token->setUserId($userId);
        $token->setToken([
            'access_token' => self::SECRET_ACCESS_TOKEN,
            'refresh_token' => self::SECRET_REFRESH_TOKEN,
        ]);
        $token->setExpiresAt(new \DateTimeImmutable($expiresAt));
        $em->persist($token);
        $em->flush();

        return $token;
    }
}
