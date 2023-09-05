<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Tests\Client\OAuthClientTestMock;
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
//            dump($response->getContent());
        }

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(getenv('KEYCLOAK_URL').'/realms/master/protocol/openid-connect/auth?client_id=test&response_type=code&redirect_uri=http%3A%2F%2Flocalhost%2Fadmin%2Foauth%2Fcheck&state=cj1odHRwJTNBJTJGJTJGbG9jYWxob3N0JTJGYWRtaW4%3D', $response->getTargetUrl());

        $this->logIn($this->client);
        $crawler = $this->client->request('GET', '/admin');
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            dump($response->getContent());
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
        return new JwtUser(OAuthClientTestMock::getJwtFor(OAuthClientTestMock::ADMIN_UID), OAuthClientTestMock::ADMIN_UID, 'admin', [
            JwtUser::ROLE_ADMIN,
        ]);
    }
}
