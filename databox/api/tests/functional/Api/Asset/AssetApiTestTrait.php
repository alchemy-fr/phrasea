<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceCreator;

/**
 * Shared helpers of the Asset lifecycle tests.
 *
 * Users: KeycloakClientTestMock::USER_UID (plain user, owns the default
 * workspace in most tests), OTHER_USER_UID (plain user) and ADMIN_UID
 * (ROLE_ADMIN: the AdminVoter grants every attribute).
 */
trait AssetApiTestTrait
{
    protected const string OWNER = KeycloakClientTestMock::USER_UID;
    protected const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;
    protected const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    /**
     * @return array{Authorization?: string}
     */
    protected static function authHeaders(?string $userId): array
    {
        if (null === $userId) {
            return [];
        }

        return ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId)];
    }

    /**
     * The entity manager is cleared first: the first request of a test runs in
     * the test kernel, whose identity map would otherwise serve stale
     * entities (e.g. an asset whose collections were added afterwards).
     */
    protected function request(string $method, string $uri, ?string $userId, ?array $json = null, array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        self::getEntityManager()->clear();
        $options['headers'] = array_merge(self::authHeaders($userId), $options['headers'] ?? []);
        if (null !== $json) {
            $options['json'] = $json;
        }

        return static::createClient()->request($method, $uri, $options);
    }

    protected function errorMessage(\Symfony\Contracts\HttpClient\ResponseInterface $response): string
    {
        $data = $response->toArray(false);

        return $data['detail'] ?? $data['hydra:description'] ?? '';
    }

    /**
     * Default workspace owned by the given user (USER_UID by default).
     */
    protected function createOwnedWorkspace(string $ownerId = self::OWNER, array $options = []): Workspace
    {
        return $this->getOrCreateDefaultWorkspace(array_merge(['ownerId' => $ownerId], $options));
    }

    /**
     * Another workspace (the shared helper always uses the same slug).
     */
    protected function createAnotherWorkspace(string $slug, string $ownerId = self::OWNER, bool $public = false): Workspace
    {
        $em = self::getEntityManager();

        $workspace = new Workspace();
        $workspace->setName('Workspace '.$slug);
        $workspace->setSlug($slug);
        $workspace->setOwnerId($ownerId);
        $workspace->setEnabledLocales(['en']);
        $workspace->setPublic($public);

        self::getService(WorkspaceCreator::class)->createWorkspace($workspace);
        $em->flush();

        $this->addUserOnWorkspace($ownerId, $workspace->getId());

        return $workspace;
    }

    protected function createFile(Workspace $workspace, array $options = []): File
    {
        $em = self::getEntityManager();

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_URL);
        $file->setPath($options['path'] ?? 'https://example.com/'.uniqid().'.jpg');
        $file->setPathPublic(true);
        $file->setType($options['type'] ?? 'image/jpeg');
        $file->setExtension('jpg');
        $file->setOriginalName($options['originalName'] ?? 'photo.jpg');
        $file->setSize($options['size'] ?? 1234);
        if (isset($options['checksum'])) {
            $file->setChecksum($options['checksum']);
        }
        if (isset($options['metadata'])) {
            $file->setMetadata($options['metadata']);
        }
        $em->persist($file);
        $em->flush();

        return $file;
    }

    protected function reloadAsset(string $id): ?Asset
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(Asset::class, $id);
    }
}
