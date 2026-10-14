<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\UserSpace;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Template\AssetDataTemplate;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * GET /asset-data-templates (served by Elasticsearch).
 */
final class AssetDataTemplateListTest extends AbstractSearchTestCase
{
    use UserSpaceTestTrait;

    private Workspace $workspace;
    private Collection $root;
    private Collection $child;
    private Collection $sibling;

    /**
     * @var array<string, string> template name => id
     */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = $this->createOtherWorkspace(self::USER, 'tpl-list');
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
        $this->root = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::USER, 'name' => 'Root']);
        $this->child = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::USER, 'name' => 'Child', 'parent' => $this->root]);
        $this->sibling = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::USER, 'name' => 'Sibling']);

        $this->createTemplate('WS', public: true);
        $this->createTemplate('WS children', public: true, includeChildren: true);
        $this->createTemplate('WS private');
        $this->createTemplate('Root', public: true, collection: $this->root);
        $this->createTemplate('Root children', public: true, collection: $this->root, includeChildren: true);
        $this->createTemplate('Child', public: true, collection: $this->child);
        $this->createTemplate('Sibling', public: true, collection: $this->sibling);
        $this->createTemplate('Other private', ownerId: self::OTHER);

        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset_data_template');
        // Templates must be re-hydrated by the request (tags are only initialized by Doctrine)
        self::getEntityManager()->clear();
    }

    public function testWorkspaceFilterIsMandatory(): void
    {
        $this->assertStatus(400, 'GET', '/asset-data-templates', self::USER);
    }

    public function testWorkspaceLevelTemplates(): void
    {
        // Without a collection, only templates not bound to a collection are returned
        $this->assertSame(
            ['WS', 'WS children', 'WS private'],
            $this->listNames(self::USER, ['workspace' => $this->workspace->getId()])
        );
    }

    public function testPrivateTemplatesOfOthersAreHidden(): void
    {
        $this->assertSame(
            ['Other private', 'WS', 'WS children'],
            $this->listNames(self::OTHER, ['workspace' => $this->workspace->getId()])
        );
    }

    public function testPrivateTemplateSharedThroughAclIsListed(): void
    {
        $template = self::getEntityManager()->find(AssetDataTemplate::class, $this->ids['WS private']);
        $this->grantUserOnObject(self::OTHER, $template, PermissionInterface::VIEW);
        self::populateSearchIndices();

        $this->assertContains('WS private', $this->listNames(self::OTHER, ['workspace' => $this->workspace->getId()]));
    }

    public function testAnonymousWithoutWorkspaceAccessGetsNothing(): void
    {
        // Even public templates require read access on the (non-public) workspace
        $this->assertSame([], $this->listNames(null, ['workspace' => $this->workspace->getId()]));
    }

    public function testCollectionIncludesInheritedTemplates(): void
    {
        // Own templates + ancestors' and workspace's templates flagged "includeCollectionChildren", closest last
        $this->assertSame(
            ['WS children', 'Root children', 'Child'],
            $this->listNames(self::USER, ['workspace' => $this->workspace->getId(), 'collection' => $this->child->getId()])
        );

        // Same depth: ordered by relevance then name
        $names = $this->listNames(self::USER, ['workspace' => $this->workspace->getId(), 'collection' => $this->root->getId()]);
        $this->assertSame('WS children', array_shift($names));
        sort($names);
        $this->assertSame(['Root', 'Root children'], $names);

        $this->assertSame(
            ['WS children', 'Sibling'],
            $this->listNames(self::USER, ['workspace' => $this->workspace->getId(), 'collection' => $this->sibling->getId()])
        );
    }

    public function testWorkspaceIsDeducedFromCollection(): void
    {
        $this->assertSame(
            ['WS children', 'Root children', 'Child'],
            $this->listNames(self::USER, ['collection' => $this->child->getId()])
        );
    }

    public function testCollectionOfAnotherWorkspaceIsRejected(): void
    {
        $foreign = $this->createOtherWorkspace(self::USER, 'tpl-foreign');

        $this->assertStatus(400, 'GET', '/asset-data-templates', self::USER, query: [
            'workspace' => $foreign->getId(),
            'collection' => $this->child->getId(),
        ]);
    }

    public function testCollectionTemplatesRequireEditOnCollection(): void
    {
        // OTHER can read the workspace but cannot edit the collection
        $this->assertSame([], $this->listNames(self::OTHER, ['workspace' => $this->workspace->getId(), 'collection' => $this->child->getId()]));
    }

    public function testQueryFiltersByName(): void
    {
        $this->assertSame(
            ['WS children'],
            $this->listNames(self::USER, ['workspace' => $this->workspace->getId(), 'query' => 'children'])
        );
    }

    private function listNames(?string $userId, array $query): array
    {
        $data = $this->apiJson('GET', '/asset-data-templates', $userId, query: $query);
        $names = array_column($this->members($data), 'name');
        if (!isset($query['collection'])) {
            sort($names);
        }

        return $names;
    }

    private function createTemplate(
        string $name,
        string $ownerId = self::USER,
        bool $public = false,
        ?Collection $collection = null,
        bool $includeChildren = false,
    ): void {
        $em = self::getEntityManager();
        $template = new AssetDataTemplate();
        $template->setName($name);
        $template->setOwnerId($ownerId);
        $template->setWorkspace($em->find(Workspace::class, $this->workspace->getId()));
        $template->setPublic($public);
        $template->setCollection($collection ? $em->find(Collection::class, $collection->getId()) : null);
        $template->setIncludeCollectionChildren($includeChildren);
        $em->persist($template);
        $em->flush();

        $this->ids[$name] = $template->getId();
    }
}
