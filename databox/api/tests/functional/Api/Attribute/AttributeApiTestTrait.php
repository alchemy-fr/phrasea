<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceCreator;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Helpers shared by the attribute related API tests.
 */
trait AttributeApiTestTrait
{
    protected const string USER = KeycloakClientTestMock::USER_UID;
    protected const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;
    protected const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    protected function api(string $method, string $uri, ?string $userId = null, ?array $json = null, array $options = []): ResponseInterface
    {
        $options['headers'] ??= [];
        if (null !== $userId) {
            $options['headers']['Authorization'] = 'Bearer '.KeycloakClientTestMock::getJwtFor($userId);
        }
        if ('PATCH' === $method) {
            $options['headers']['Content-Type'] ??= 'application/merge-patch+json';
        }
        if (null !== $json) {
            $options['json'] = $json;
        }

        return static::createClient()->request($method, $uri, $options);
    }

    /**
     * Same as createWorkspace(), with a distinct slug so that several workspaces can coexist.
     */
    protected function createOtherWorkspace(array $options = []): Workspace
    {
        $workspace = new Workspace();
        $workspace->setName($options['name'] ?? 'Other workspace');
        $ownerId = $options['ownerId'] ?? 'custom_owner';
        $workspace->setOwnerId($ownerId);
        $workspace->setEnabledLocales(['fr', 'en']);
        $workspace->setSlug($options['slug'] ?? 'other-workspace-'.substr(md5(uniqid('', true)), 0, 8));
        if ($options['public'] ?? false) {
            $workspace->setPublic(true);
        }

        /** @var WorkspaceCreator $workspaceCreator */
        $workspaceCreator = self::getService(WorkspaceCreator::class);
        $workspaceCreator->createWorkspace($workspace);
        $this->addUserOnWorkspace($ownerId, $workspace->getId());
        self::getEntityManager()->flush();

        return $workspace;
    }

    protected function grantOnWorkspace(string $userId, Workspace $workspace, int $permission): void
    {
        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            $userId,
            'workspace',
            $workspace->getId(),
            $permission
        );
    }

    protected function createEntityList(array $options = []): EntityList
    {
        $em = self::getEntityManager();

        $list = new EntityList();
        $list->setName($options['name'] ?? 'List');
        $list->setWorkspace($options['workspace'] ?? $this->getOrCreateDefaultWorkspace());
        if (isset($options['allowNewValues'])) {
            $list->setAllowNewValues($options['allowNewValues']);
        }
        if (isset($options['approveNewValues'])) {
            $list->setApproveNewValues($options['approveNewValues']);
        }
        $em->persist($list);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $list;
    }

    protected function createEntity(EntityList $list, string $value, array $options = []): AttributeEntity
    {
        $em = self::getEntityManager();

        $entity = new AttributeEntity();
        $entity->setList($list);
        $entity->setValue($value);
        $entity->setStatus($options['status'] ?? AttributeEntity::STATUS_APPROVED);
        $entity->setCreatorId($options['creatorId'] ?? null);
        $entity->setSynonyms($options['synonyms'] ?? null);
        $entity->setTranslations($options['translations'] ?? null);
        $em->persist($entity);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $entity;
    }

    /**
     * @return string[]
     */
    protected static function ids(ResponseInterface $response): array
    {
        return array_column($response->toArray()['member'], 'id');
    }
}
