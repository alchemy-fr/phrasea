<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceCreator;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Shared helpers of the collection API tests.
 *
 * The workspace is owned by a third party ("ws_owner"), so that no tested user
 * gets an implicit right from the workspace ownership.
 */
trait CollectionTestTrait
{
    protected const string WS_OWNER = 'ws_owner';
    protected const string USER = KeycloakClientTestMock::USER_UID;
    protected const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;
    protected const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    protected const string ANONYMOUS = 'anonymous';

    /**
     * Separator of the absolute names (non-breaking spaces around the slash).
     */
    protected const string SEP = "\u{a0}/\u{a0}";

    protected function createTestWorkspace(array $options = []): Workspace
    {
        $options = array_merge([
            'ownerId' => self::WS_OWNER,
        ], $options);

        if (null === $this->defaultWorkspace) {
            $workspace = $this->defaultWorkspace = $this->createWorkspace($options);
        } else {
            $count = 1 + self::getEntityManager()->getRepository(Workspace::class)->count([]);
            // DataboxTestTrait::createWorkspace() always uses the same slug
            $workspace = new Workspace();
            $workspace->setName('Workspace '.$count);
            $workspace->setOwnerId($options['ownerId']);
            $workspace->setEnabledLocales(['fr', 'en', 'de']);
            $workspace->setSlug('my-workspace-'.$count);
            $workspace->setPublic($options['public'] ?? false);
            self::getService(WorkspaceCreator::class)->createWorkspace($workspace);
            $this->addUserOnWorkspace($options['ownerId'], $workspace->getId());
            self::getEntityManager()->flush();
        }

        foreach ($options['members'] ?? [] as $member) {
            $this->addUserOnWorkspace($member, $workspace->getId());
        }

        return $workspace;
    }

    protected function request(string $method, string $uri, ?string $userId = null, array $options = []): ResponseInterface
    {
        $headers = $options['headers'] ?? [];
        if (null !== $userId && self::ANONYMOUS !== $userId) {
            $headers['Authorization'] = 'Bearer '.KeycloakClientTestMock::getJwtFor($userId);
        }
        if ('PATCH' === $method) {
            $headers['Content-Type'] ??= 'application/merge-patch+json';
        }
        $options['headers'] = $headers;

        return static::createClient()->request($method, $uri, $options);
    }

    protected function findCollection(string $id): ?Collection
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(Collection::class, $id);
    }

    protected function findAssetById(string $id): ?Asset
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(Asset::class, $id);
    }

    protected function findCollectionAsset(string $id): ?CollectionAsset
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(CollectionAsset::class, $id);
    }

    protected function setCollectionPrivacy(Collection $collection, int $privacy): void
    {
        $collection->setPrivacy($privacy);
        $em = self::getEntityManager();
        $em->persist($collection);
        $em->flush();
    }
}
