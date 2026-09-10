<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\Search\ESPopulate;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class PopulatePassAdminTest extends AbstractAdminTest
{
    private InMemoryTransport $inMemory;

    public function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        // The kernel must survive the redirect, otherwise the intercepting
        // transport is rebuilt and forgets what was sent
        $this->client->disableReboot();

        /** @var TestTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.p2');
        $this->inMemory = $transport->intercept();
    }

    public function testAddPageListsTheIndices(): void
    {
        $crawler = $this->loadAddPage();

        $this->assertCount(1, $crawler->filter('input[name="scope"][value="all"]'));
        $this->assertCount(1, $crawler->filter('input[name="scope"][value="one"]'));
        $options = $crawler->filter('select[name="index"] option')->each(fn (Crawler $o): string => $o->attr('value'));
        $this->assertContains('asset', $options);
        $this->assertContains('collection', $options);
        $this->assertSame([], $this->getPopulateMessages(), 'displaying the form must not trigger anything');
    }

    public function testPopulateAllIndices(): void
    {
        $crawler = $this->loadAddPage();
        $form = $crawler->selectButton('Start populate')->form();

        // "all": the select is disabled client-side, so no "index" value is submitted
        $this->client->request('POST', $form->getUri(), ['token' => $form->get('token')->getValue(), 'scope' => 'all']);
        $this->assertResponseRedirects();
        // read before following the redirect: the next request resets the in-memory transport
        $messages = $this->getPopulateMessages();
        $this->assertCount(1, $messages);
        $this->assertNull($messages[0]->index);

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Populate of all indices was triggered', $crawler->filter('#flash-messages')->text());
    }

    public function testPopulateASingleIndex(): void
    {
        $crawler = $this->loadAddPage();
        $form = $crawler->selectButton('Start populate')->form();

        $this->client->request('POST', $form->getUri(), ['token' => $form->get('token')->getValue(), 'scope' => 'one', 'index' => 'asset']);
        $this->assertResponseRedirects();
        $messages = $this->getPopulateMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('asset', $messages[0]->index);

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Populate of index "asset" was triggered', $crawler->filter('#flash-messages')->text());
    }

    public function testUnknownIndexIsRejected(): void
    {
        $crawler = $this->loadAddPage();
        $form = $crawler->selectButton('Start populate')->form();

        $this->client->request('POST', $form->getUri(), ['token' => $form->get('token')->getValue(), 'scope' => 'one', 'index' => 'nope']);
        $this->assertResponseRedirects();
        $this->assertSame([], $this->getPopulateMessages());

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Unknown index "nope"', $crawler->filter('#flash-messages')->text());
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $crawler = $this->loadAddPage();
        $form = $crawler->selectButton('Start populate')->form();

        $this->client->request('POST', $form->getUri(), ['token' => 'invalid', 'scope' => 'all']);
        $this->assertResponseRedirects();
        $this->assertSame([], $this->getPopulateMessages());

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Invalid CSRF token', $crawler->filter('#flash-messages')->text());
    }

    private function loadAddPage(): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/populate-pass');
        $this->assertResponseIsSuccessful();
        $link = $crawler->filter('a.action-AddPopulate');
        $this->assertCount(1, $link, 'the global "Add Populate" action is on the list page');

        $crawler = $this->client->request('GET', $link->attr('href'));
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            echo $response->getContent();
        }
        $this->assertEquals(200, $response->getStatusCode());

        return $crawler;
    }

    /**
     * @return list<ESPopulate>
     */
    private function getPopulateMessages(): array
    {
        $messages = array_map(static fn ($envelope) => $envelope->getMessage(), $this->inMemory->getSent());

        return array_values(array_filter($messages, static fn ($m): bool => $m instanceof ESPopulate));
    }
}
