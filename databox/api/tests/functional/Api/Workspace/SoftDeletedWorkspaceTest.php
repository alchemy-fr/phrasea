<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Workspace;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * A soft-deleted workspace waits for the DeleteWorkspace message: in the meantime,
 * neither the workspace nor what it contains may be reached, by its owner included.
 */
final class SoftDeletedWorkspaceTest extends AbstractDataboxTestCase
{
    private const string USER = KeycloakClientTestMock::USER_UID;

    /**
     * @return array<string, string> ids of the workspace content, by kind
     */
    private function createPopulatedWorkspace(array $options): array
    {
        $em = self::getEntityManager();
        $workspace = $this->createWorkspace($options);

        $tag = $this->findOrCreateTagEntity('Soft deleted entity', $workspace);

        $list = new EntityList();
        $list->setName('Soft deleted list');
        $list->setWorkspace($workspace);
        $em->persist($list);

        $policy = $this->createAttributePolicy([
            'workspace' => $workspace,
            'name' => 'Soft deleted policy',
            'no_flush' => true,
        ]);
        $definition = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'policy' => $policy,
            'name' => 'Soft deleted definition',
            'no_flush' => true,
        ]);

        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setIntegration('core.webhook');
        $integration->setConfig(['url' => 'https://hook.phrasea.test']);
        $integration->setPublic(false);
        $integration->setOwnerId(self::USER);
        $em->persist($integration);

        $em->flush();

        return [
            'workspace' => $workspace->getId(),
            'tag' => $tag->getId(),
            'list' => $list->getId(),
            'policy' => $policy->getId(),
            'definition' => $definition->getId(),
            'integration' => $integration->getId(),
        ];
    }

    private function softDelete(string $workspaceId): void
    {
        $em = self::getEntityManager();
        $em->clear();
        $em->find(Workspace::class, $workspaceId)->setDeletedAt(new \DateTimeImmutable());
        $em->flush();
    }

    /**
     * A new client each time: the workspace voter caches its decisions.
     *
     * @return array{0: int, 1: string[]} the status code and the listed ids
     */
    private static function fetchIds(string $uri, ?string $userId, array $query = []): array
    {
        $options = ['query' => $query];
        if (null !== $userId) {
            $options['headers'] = ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId)];
        }

        $response = static::createClient()->request('GET', $uri, $options);
        $status = $response->getStatusCode();

        return [$status, 200 === $status ? array_column($response->toArray()['member'], 'id') : []];
    }

    public function testSoftDeletedWorkspaceContentIsUnreachableByItsOwner(): void
    {
        $ids = $this->createPopulatedWorkspace(['ownerId' => self::USER, 'slug' => 'soft-deleted-ws']);
        $ws = $ids['workspace'];

        $this->assertSame([200, [$ids['tag']]], self::fetchIds('/attribute-entities', self::USER, ['workspace' => $ws]));
        $this->assertContains($ids['tag'], self::fetchIds('/attribute-entities', self::USER)[1]);
        $this->assertContains($ids['list'], self::fetchIds('/entity-lists', self::USER)[1]);
        $this->assertContains($ids['policy'], self::fetchIds('/attribute-policies', self::USER, ['workspaceId' => $ws])[1]);
        $this->assertContains($ids['definition'], self::fetchIds('/attribute-definitions', self::USER, ['workspaceId' => $ws])[1]);
        $this->assertContains($ids['integration'], self::fetchIds('/integrations', self::USER)[1]);

        $this->softDelete($ws);

        $this->assertSame(403, self::fetchIds('/attribute-entities', self::USER, ['workspace' => $ws])[0]);
        $this->assertNotContains($ids['tag'], self::fetchIds('/attribute-entities', self::USER)[1]);
        $this->assertNotContains($ids['list'], self::fetchIds('/entity-lists', self::USER)[1]);
        $this->assertSame([200, []], self::fetchIds('/attribute-policies', self::USER, ['workspaceId' => $ws]));
        $this->assertSame([200, []], self::fetchIds('/attribute-definitions', self::USER, ['workspaceId' => $ws]));
        $this->assertNotContains($ids['integration'], self::fetchIds('/integrations', self::USER)[1]);
        $this->assertSame(403, self::fetchIds('/integrations', self::USER, ['workspace' => $ws])[0]);
    }

    public function testSoftDeletedPublicWorkspaceIsUnreachableAnonymously(): void
    {
        $ids = $this->createPopulatedWorkspace(['public' => true, 'slug' => 'soft-deleted-public-ws']);
        $ws = $ids['workspace'];

        $this->assertSame([200, [$ids['tag']]], self::fetchIds('/attribute-entities', null, ['workspace' => $ws]));

        $this->softDelete($ws);

        $this->assertContains(self::fetchIds('/attribute-entities', null, ['workspace' => $ws])[0], [401, 403]);
        $this->assertNotContains($ids['tag'], self::fetchIds('/attribute-entities', null)[1]);
    }
}
