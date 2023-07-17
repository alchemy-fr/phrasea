<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AuthBundle\Security\JwtUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractAdminTest extends WebTestCase
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
            dump($response->getContent());
        }
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/admin/login?r=http://localhost/admin', $response->getTargetUrl());

        $this->logIn($this->client);
        $crawler = $this->client->request('GET', '/admin');
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
//            dump($response->getContent());
        }
        $this->assertEquals(200, $response->getStatusCode());

        $crawler
            ->filter('nav#main-menu ul.submenu a')
            ->each(function (Crawler $node): void {
                if ('#' !== $href = $node->attr('href')) {
                    $this->assertMatchesRegularExpression('#^http://localhost/admin\?.+$#', $href);
                    $this->explore($href);
                }
            });
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
            dump($response->getContent());
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
        return new JwtUser('123', 'admin', [
            'ROLE_SUPER_ADMIN',
        ]);
    }
}
