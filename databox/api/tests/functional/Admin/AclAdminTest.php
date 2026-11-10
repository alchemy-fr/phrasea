<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTestCase;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

class AclAdminTest extends AbstractAdminTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
    }

    public function testMenuLinkOpensTheTypeWidePage(): void
    {
        $crawler = $this->client->request('GET', '/admin/asset');
        $this->assertResponseIsSuccessful();

        $crawler = $this->client->click($crawler->selectLink('Asset permissions')->link());

        $this->assertResponseIsSuccessful();
        $this->assertSame('/admin/asset/permissions/all', $this->client->getRequest()->getPathInfo());
        $this->assertSelectorTextContains('h1', 'Permissions on all asset');
        $this->assertSame('/admin/asset', $this->getButtonPath($crawler, 'Back'));
        $this->assertCount(1, $crawler->filter('[data-object-type="asset"][data-object-id=""]'));
    }

    public function testObjectPageLinksToTheTypeWidePage(): void
    {
        $workspace = $this->createWorkspace();

        $crawler = $this->client->request('GET', '/admin/workspace');
        $this->assertResponseIsSuccessful();
        $this->assertSame('/admin/workspace/permissions/all', parse_url($crawler->filter('a.action-globalPermissions')->attr('href'), PHP_URL_PATH));

        $crawler = $this->client->click($crawler->filter('a.action-permissions')->link());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Permissions on workspace');
        $this->assertCount(1, $crawler->filter(sprintf('[data-object-type="workspace"][data-object-id="%s"]', $workspace->getId())));
        $this->assertSame('/admin/workspace', $this->getButtonPath($crawler, 'Back'));
        $this->assertSame('/admin/workspace/permissions/all', $this->getButtonPath($crawler, 'Permissions on all workspace'));

        $this->client->click($crawler->selectLink('Permissions on all workspace')->link());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Permissions on all workspace');
    }

    public function testTypeWidePageIsOnlyServedUnderTheAdmin(): void
    {
        $this->client->request('GET', '/acl/aces/asset/global');
        $this->assertResponseStatusCodeSame(404);

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/admin/asset/permissions/all');
        $this->assertResponseRedirects();
    }

    private function getButtonPath(Crawler $crawler, string $label): string
    {
        return parse_url($crawler->selectLink($label)->attr('href'), PHP_URL_PATH);
    }

    private function createWorkspace(): Workspace
    {
        $workspace = new Workspace();
        $workspace->setName('ACL admin workspace');
        $workspace->setSlug('acl-admin-workspace');
        $workspace->setOwnerId('custom_owner');
        $workspace->setEnabledLocales(['en']);
        static::getContainer()->get(WorkspaceCreator::class)->createWorkspace($workspace);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $workspace;
    }
}
