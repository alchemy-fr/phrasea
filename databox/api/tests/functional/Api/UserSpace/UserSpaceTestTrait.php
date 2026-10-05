<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\UserSpace;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceCreator;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Small HTTP helpers shared by the "user space" API tests
 * (baskets, profiles, saved searches, pages, templates).
 */
trait UserSpaceTestTrait
{
    protected const string USER = KeycloakClientTestMock::USER_UID;
    protected const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;
    protected const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    protected static function authHeaders(?string $userId): array
    {
        if (null === $userId) {
            return [];
        }

        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }

    /**
     * @param string|null $userId null for an anonymous request
     */
    protected function api(string $method, string $uri, ?string $userId = self::USER, ?array $json = null, array $query = [], array $headers = []): ResponseInterface
    {
        $options = [
            'headers' => array_merge(self::authHeaders($userId), $headers),
        ];
        if (null !== $json) {
            $options['json'] = $json;
        }
        if (!empty($query)) {
            $options['query'] = $query;
        }

        return static::createClient()->request($method, $uri, $options);
    }

    /**
     * @return array<string, mixed>
     */
    protected function apiJson(string $method, string $uri, ?string $userId = self::USER, ?array $json = null, array $query = [], ?int $expectedStatus = null): array
    {
        $expectedStatus ??= 'POST' === $method ? 201 : 200;
        $response = $this->api($method, $uri, $userId, $json, $query);
        $this->assertSame(
            $expectedStatus,
            $response->getStatusCode(),
            sprintf('%s %s: unexpected status. Body: %s', $method, $uri, $response->getContent(false))
        );

        if (204 === $expectedStatus) {
            return [];
        }

        return $response->toArray(false);
    }

    protected function assertStatus(int $expected, string $method, string $uri, ?string $userId = self::USER, ?array $json = null, array $query = []): void
    {
        $response = $this->api($method, $uri, $userId, $json, $query);
        $this->assertSame(
            $expected,
            $response->getStatusCode(),
            sprintf('%s %s as %s: unexpected status. Body: %s', $method, $uri, $userId ?? 'anonymous', $response->getContent(false))
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function members(array $collection): array
    {
        return $collection['hydra:member'] ?? $collection['member'] ?? [];
    }

    protected function memberIds(array $collection): array
    {
        return array_column($this->members($collection), 'id');
    }

    /**
     * Like createWorkspace() but with a distinct slug, so several workspaces can coexist.
     */
    protected function createOtherWorkspace(string $ownerId, string $slug): Workspace
    {
        $workspace = new Workspace();
        $workspace->setName($slug);
        $workspace->setSlug($slug);
        $workspace->setOwnerId($ownerId);
        $workspace->setEnabledLocales(['fr', 'en']);

        /** @var WorkspaceCreator $workspaceCreator */
        $workspaceCreator = self::getService(WorkspaceCreator::class);
        $workspaceCreator->createWorkspace($workspace);
        $this->addUserOnWorkspace($ownerId, $workspace->getId());
        self::getEntityManager()->flush();

        return $workspace;
    }
}
