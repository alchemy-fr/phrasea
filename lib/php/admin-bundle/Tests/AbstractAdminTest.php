<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

abstract class AbstractAdminTest extends WebTestCase
{
    protected KernelBrowser $client;

    public function setUp(): void
    {
        parent::setUp();
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
        $this->assertEquals('/admin/login?r=http%3A%2F%2Flocalhost%2Fadmin', $response->getTargetUrl());

        $this->client->followRedirects();
        $this->logIn();
        $crawler = $this->client->request('GET', '/admin');
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            dump($response->getContent());
        }
        $this->assertEquals(200, $response->getStatusCode());

        $crawler
            ->filter('nav#main-menu ul.submenu a')
            ->each(function ($node, $i) {
                if ('#' !== $href = $node->attr('href')) {
                    $this->assertMatchesRegularExpression('#^http://localhost/admin\?.+$#', $href);
                    $this->explore($href);
                }
            });
    }

    private function explore(string $path)
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

    private function logIn()
    {
        $session = self::$container->get('session');

        $user = $this->getAuthAdminUser();

        $firewallName = 'admin';
        $token = new PostAuthenticationGuardToken($user, $firewallName, $user->getRoles());
        $session->set('_security_'.$firewallName, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    protected function getAuthAdminUser(): UserInterface
    {
        return new RemoteUser('123', 'admin', [
            'ROLE_SUPER_ADMIN',
        ]);
    }
}
