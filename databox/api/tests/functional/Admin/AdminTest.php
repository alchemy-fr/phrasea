<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\TestBundle\Helper\TestServicesTrait;
use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;
use App\Entity\Core\Collection;
use App\Entity\Log\AuditLog;
use App\Model\AuditActorTypeEnum;
use App\Model\AuditLogActionEnum;
use App\Tests\Functional\DataboxTestTrait;

class AdminTest extends AbstractDataboxAdminTestCase
{
    use DataboxTestTrait;
    use TestServicesTrait;

    private const array HOME_STAT_LABELS = [
        'Assets',
        'Collections',
        'Workspaces',
        'Workflows',
        'Failed workflows',
        'Exports',
        'Shares',
        'Baskets',
        'Active users (30 days)',
        'Failed messages',
    ];
    private const array HOME_BLOCK_TITLES = [
        'Latest assets',
        'Audit log',
        'Workflows',
        'Exports',
    ];
    private const array HOME_CHART_TITLES = [
        'Assets created (30 days)',
        'Assets by file family',
        'Assets by workspace',
    ];
    /**
     * Read from Elasticsearch, which may be empty or unreachable: these then show an error,
     * but the page is still rendered.
     */
    private const array HOME_ES_SOURCES = [
        'Assets',
        'Latest assets',
        'Assets created (30 days)',
        'Assets by file family',
        'Assets by workspace',
    ];

    public function testAdmin()
    {
        $this->doTestAllPages();
    }

    public function testHomePage(): void
    {
        $this->doTestHomePage(self::HOME_STAT_LABELS, self::HOME_BLOCK_TITLES, self::HOME_CHART_TITLES, self::HOME_ES_SOURCES);
    }

    public function testHomePageFigures(): void
    {
        $em = $this->getEntityManager();

        $log = new AuditLog(new \DateTimeImmutable());
        $log->setAction(AuditLogActionEnum::ObjectCreated);
        $log->setActorType(AuditActorTypeEnum::User);
        $log->setUserId(KeycloakClientTestMock::USER_UID);
        $log->setObjectType(Collection::OBJECT_INDEX);
        $log->setObjectId('collection-1');
        $em->persist($log);

        $this->createCollection(['name' => 'Counted collection']);
        $story = $this->createAsset();
        $storyCollection = new Collection();
        $storyCollection->setWorkspace($story->getWorkspace());
        $storyCollection->setOwnerId('custom_owner');
        $em->persist($storyCollection);
        $story->setStoryCollection($storyCollection);
        $em->flush();

        $repository = static::getContainer()->get(StateRepositoryInterface::class);
        $repository->persistWorkflowState(new WorkflowState($repository, 'test', new WorkflowEvent('test')));

        $crawler = $this->doTestHomePage(self::HOME_STAT_LABELS, self::HOME_BLOCK_TITLES, self::HOME_CHART_TITLES, self::HOME_ES_SOURCES);

        $workflows = $this->getHomeStatFigures($crawler, 'Workflows');
        $this->assertSame('1', $workflows['total']);
        $this->assertSame('1', $workflows['periods']['24h']);
        $this->assertSame('1', $this->getHomeStatFigures($crawler, 'Active users (30 days)')['periods']['24h']);
        // A story is backed by a collection, which is not counted as one
        $this->assertSame('1', $this->getHomeStatFigures($crawler, 'Collections')['total']);
    }

    public function testRealtimePage(): void
    {
        $this->doTestRealtimePage();
    }

    public function testQueuesPage(): void
    {
        $this->doTestQueuesPage();
    }
}
