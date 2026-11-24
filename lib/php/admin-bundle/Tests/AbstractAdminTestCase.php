<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Controller\QueuesListController;
use Alchemy\AdminBundle\Controller\RealtimeStatusController;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractAdminTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function doTestAllPages(): void
    {
        $this->client->request('GET', '/admin');
        $this->client->followRedirects();
        $response = $this->client->getResponse();
        if (302 !== $response->getStatusCode()) {
            // dump($response->getContent());
        }

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(sprintf(
            '%s/realms/phrasea/protocol/openid-connect/auth?client_id=%s&response_type=code&redirect_uri=%s&state=%s',
            getenv('KEYCLOAK_URL'),
            getenv('ADMIN_CLIENT_ID'),
            urlencode('http://localhost/admin/oauth/check'),
            'cj1odHRwJTNBJTJGJTJGbG9jYWxob3N0JTJGYWRtaW4%3D',
        ), $response->getTargetUrl());

        $this->logIn($this->client);
        $crawler = $this->client->request('GET', '/admin');
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            echo $response->getContent();
        }
        $this->assertEquals(200, $response->getStatusCode());

        // Every menu entry, submenu toggles included, has a Font Awesome icon
        $crawler->filter('nav#main-menu .ea-sidebar-item-link')
            ->each(function (Crawler $node): void {
                $this->assertCount(
                    1,
                    $node->filter('.ea-sidebar-item-icon i[class*="fa-"]'),
                    sprintf('No icon on the admin menu entry "%s"', trim($node->filter('.ea-sidebar-item-label')->text(''))),
                );
            });

        $menuLinks = $crawler->filter('nav#main-menu a.ea-sidebar-item-link');
        $this->assertGreaterThan(0, $menuLinks->count(), 'No link found in the admin menu');
        $menuLinks
            ->each(function (Crawler $node): void {
                if ('#' !== $href = $node->attr('href')) {
                    $this->assertMatchesRegularExpression('#^http://localhost/admin([/?].*)?$#', $href);
                    // The home page (dashboard link) is the page being explored
                    if ('http://localhost/admin' !== $href) {
                        $this->explore($href);
                    }
                }
            });
    }

    /**
     * @param string[] $expectedStatLabels
     * @param string[] $expectedBlockTitles
     */
    protected function doTestHomePage(array $expectedStatLabels, array $expectedBlockTitles): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        $crawler = $this->loadPage('/admin');

        $this->assertStringContainsString('Home', $crawler->filter('title')->text());
        $this->assertCount(1, $crawler->filter('nav#main-menu a.ea-sidebar-item-link[href="http://localhost/admin"]'), 'No Home link in the admin menu');

        $statLabels = $crawler->filter('.admin-home-stat')->each(static fn (Crawler $node): string => trim($node->filter('.card-body > div')->first()->text()));
        foreach ($expectedStatLabels as $label) {
            $this->assertContains($label, $statLabels, sprintf('No "%s" card on the admin home page', $label));
        }

        $blockTitles = $crawler->filter('.admin-home-block .card-header > span')->each(static fn (Crawler $node): string => trim($node->text()));
        foreach ($expectedBlockTitles as $title) {
            $this->assertContains($title, $blockTitles, sprintf('No "%s" block on the admin home page', $title));
        }
    }

    protected function doTestRealtimePage(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        $this->client->followRedirects();
        $path = static::getContainer()->get('router')->generate(RealtimeStatusController::ROUTE_NAME);
        $this->assertSame('/admin/realtime', $path);

        $crawler = $this->client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter(sprintf('nav#main-menu a.ea-sidebar-item-link[href$="%s"]', $path)), 'No Realtime link in the admin menu');

        $crawler = $this->loadPage($path);
        $this->assertStringContainsString('Realtime (Sockudo)', $crawler->filter('title')->text());
        $this->assertStringContainsString('Disabled', (string) $this->client->getResponse()->getContent());
    }

    protected function doTestQueuesPage(): void
    {
        $router = static::getContainer()->get('router');
        // The test environment has no AMQP transport: no queue is listed nor can be inspected
        $inspectPath = $router->generate(QueuesListController::ROUTE_INSPECT, ['queue' => 'p1']);

        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        $this->client->request('GET', $router->generate(QueuesListController::ROUTE_LIST));
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No RabbitMQ transport is configured', (string) $this->client->getResponse()->getContent());

        $this->client->request('POST', $inspectPath, ['token' => 'invalid']);
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', $inspectPath);
        $this->assertResponseStatusCodeSame(405);
    }

    private function explore(string $path): void
    {
        $crawler = $this->loadPage($path);

        $crawler
            ->filter('a.action-new')
            ->each(function ($node, $i) {
                if ('#' !== $href = $node->attr('href')) {
                    $this->loadPage($href);
                }
            });

        $crawler
            ->filter('a.action-permissions')
            ->each(function ($node, $i) {
                if ('#' !== $href = $node->attr('href')) {
                    $this->loadPage($href);
                }
            });
    }

    private function loadPage(string $path): Crawler
    {
        $crawler = $this->client->request('GET', $path);
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            echo $response->getContent();
        }
        $this->assertEquals(200, $response->getStatusCode(), 'On page: '.$path);

        return $crawler;
    }

    private function logIn(KernelBrowser $client): void
    {
        $user = $this->getAuthAdminUser();
        $client->loginUser($user, 'admin');
    }

    protected function getAuthAdminUser(): UserInterface
    {
        return new JwtUser(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), KeycloakClientTestMock::ADMIN_UID, 'admin', [
            JwtUser::ROLE_ADMIN,
        ]);
    }
}
