<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Alchemy\TestBundle\Helper\TestServicesTrait;
use App\Entity\Automation\AutomationShare;
use App\Entity\Core\Share;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceCreator;
use App\Tests\Functional\Automation\AutomationTestTrait;
use Doctrine\DBAL\Connection;
use Symfony\Component\DomCrawler\Crawler;

class AutomationShareAdminTest extends AbstractDataboxAdminTestCase
{
    use TestServicesTrait;
    use AutomationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
    }

    public function testDeleteRemovesTheShare(): void
    {
        [$kept, $keptShare] = $this->createAutomationShare('kept');
        [$deleted, $deletedShare] = $this->createAutomationShare('deleted');

        $crawler = $this->loadIndexPage();
        $link = $crawler->filter(sprintf('tr[data-id="%s"] .action-delete', $deleted));
        $this->assertCount(1, $link);

        $this->client->request('POST', $link->attr('href'), [
            'token' => $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value'),
        ]);
        $this->assertResponseRedirects();

        $this->assertSame([$kept], $this->fetchIds('automation_share'));
        $this->assertSame([$keptShare], $this->fetchIds('share'));
        $this->assertNotContains($deletedShare, $this->fetchIds('share'));
    }

    public function testBatchDeleteRemovesTheShares(): void
    {
        [$kept, $keptShare] = $this->createAutomationShare('kept');
        [$first] = $this->createAutomationShare('first');
        [$second] = $this->createAutomationShare('second');

        $button = $this->loadIndexPage()->filter('[data-action-name="batchDelete"]');
        $this->assertCount(1, $button);

        $this->client->request('POST', $button->attr('data-action-url'), [
            'batchActionName' => 'batchDelete',
            'entityFqcn' => AutomationShare::class,
            'batchActionEntityIds' => [$first, $second],
            'batchActionCsrfToken' => $button->attr('data-action-csrf-token'),
        ]);
        $this->assertResponseRedirects();

        $this->assertSame([$kept], $this->fetchIds('automation_share'));
        $this->assertSame([$keptShare], $this->fetchIds('share'));
    }

    /**
     * @return array{string, string} The IDs of the automation share and of its share
     */
    private function createAutomationShare(string $name): array
    {
        $em = $this->getEntityManager();

        $workspace = new Workspace();
        $workspace->setName($name);
        $workspace->setSlug('automation-share-'.$name);
        $workspace->setOwnerId('custom_owner');
        $workspace->setEnabledLocales(['en']);
        self::getService(WorkspaceCreator::class)->createWorkspace($workspace);

        $automation = $this->createAutomationEntity($this->createSavedSearchWith('custom_owner', ['query' => '']), [], options: ['name' => $name]);

        $share = new Share();
        $share->setName($name);
        $share->setOwnerId('custom_owner');
        $em->persist($share);
        $automationShare = new AutomationShare($automation, $workspace, $share);
        $em->persist($automationShare);
        $em->flush();

        return [$automationShare->getId(), $share->getId()];
    }

    private function loadIndexPage(): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/automation-share');
        $this->assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * @return list<string>
     */
    private function fetchIds(string $table): array
    {
        $ids = self::getService(Connection::class)->fetchFirstColumn(sprintf('SELECT id FROM %s', $table));
        sort($ids);

        return $ids;
    }
}
