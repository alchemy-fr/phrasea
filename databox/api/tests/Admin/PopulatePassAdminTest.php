<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\Search\ESPopulate;
use App\Elasticsearch\Listener\PopulatePassListener;
use App\Elasticsearch\PopulateLockManager;
use App\Entity\Admin\PopulatePass;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Lock\LockFactory;
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
        $this->assertCount(1, $crawler->filter('input[name="scope"][value="selected"]'));
        $options = $crawler->filter('input[name="indices[]"]')->each(fn (Crawler $o): string => $o->attr('value'));
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

    public function testPopulateSelectedIndicesQueuesOneMessagePerIndex(): void
    {
        $crawler = $this->loadAddPage();
        $form = $crawler->selectButton('Start populate')->form();

        $this->client->request('POST', $form->getUri(), ['token' => $form->get('token')->getValue(), 'scope' => 'selected', 'indices' => ['asset', 'collection', 'asset']]);
        $this->assertResponseRedirects();
        $messages = $this->getPopulateMessages();
        $this->assertSame(['asset', 'collection'], array_map(static fn (ESPopulate $m): ?string => $m->index, $messages), 'duplicates are ignored');

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Populate of 2 index(es) was triggered: asset, collection', $crawler->filter('#flash-messages')->text());
    }

    public function testSelectedScopeWithoutAnyIndexIsRejected(): void
    {
        $crawler = $this->loadAddPage();
        $form = $crawler->selectButton('Start populate')->form();

        $this->client->request('POST', $form->getUri(), ['token' => $form->get('token')->getValue(), 'scope' => 'selected']);
        $this->assertResponseRedirects();
        $this->assertSame([], $this->getPopulateMessages());

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Select at least one index', $crawler->filter('#flash-messages')->text());
    }

    public function testUnknownIndexIsRejected(): void
    {
        $crawler = $this->loadAddPage();
        $form = $crawler->selectButton('Start populate')->form();

        $this->client->request('POST', $form->getUri(), ['token' => $form->get('token')->getValue(), 'scope' => 'selected', 'indices' => ['asset', 'nope']]);
        $this->assertResponseRedirects();
        $this->assertSame([], $this->getPopulateMessages(), 'nothing is queued when one of the indices is unknown');

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Unknown index(es): nope', $crawler->filter('#flash-messages')->text());
    }

    public function testAnIndexWithARunningPassCannotBePopulatedAgain(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $running = new PopulatePass();
        $running->setIndexName('asset');
        $running->setMapping([]);
        $running->setDocumentCount(10);
        $running->setProgress(3);
        $em->persist($running);
        $em->flush();

        try {
            $crawler = $this->loadAddPage();
            $this->assertStringContainsString('A populate is currently running for: asset', $crawler->filter('.alert-warning')->text());
            $this->assertCount(1, $crawler->filter('input[name="indices[]"][value="asset"][data-running="1"]'));
            $form = $crawler->selectButton('Start populate')->form();
            $token = $form->get('token')->getValue();

            // the running index, among others
            $this->client->request('POST', $form->getUri(), ['token' => $token, 'scope' => 'selected', 'indices' => ['collection', 'asset']]);
            $this->assertResponseRedirects();
            $this->assertSame([], $this->getPopulateMessages(), 'nothing is queued, not even the free index');
            $crawler = $this->client->followRedirect();
            $this->assertStringContainsString('A populate is still running for: asset', $crawler->filter('#flash-messages')->text());

            // "all" is refused as well
            $this->client->request('POST', $form->getUri(), ['token' => $token, 'scope' => 'all']);
            $this->assertResponseRedirects();
            $this->assertSame([], $this->getPopulateMessages());

            // other indices are still allowed
            $this->client->request('POST', $form->getUri(), ['token' => $token, 'scope' => 'selected', 'indices' => ['collection']]);
            $this->assertResponseRedirects();
            $this->assertSame(['collection'], array_map(static fn (ESPopulate $m): ?string => $m->index, $this->getPopulateMessages()));
        } finally {
            $em->remove($em->find(PopulatePass::class, $running->getId()) ?? $running);
            $em->flush();
        }
    }

    public function testALockedIndexIsRefusedAndCanBeForceUnlocked(): void
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = static::getContainer()->get(LockFactory::class);
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);
        $deadWorker = $lockFactory->createLock(PopulatePassListener::getLockName('tag'), 600);
        $this->assertTrue($deadWorker->acquire());

        try {
            $crawler = $this->loadAddPage();
            $this->assertStringContainsString('A populate is currently running for: tag', $crawler->filter('.alert-warning')->text());
            $form = $crawler->selectButton('Start populate')->form();
            $token = $form->get('token')->getValue();

            // refused while the lock is held, even without any open pass
            $this->client->request('POST', $form->getUri(), ['token' => $token, 'scope' => 'selected', 'indices' => ['tag']]);
            $this->assertResponseRedirects();
            $this->assertSame([], $this->getPopulateMessages());

            // the "Release lock" button posts to the unlock action
            $unlock = $crawler->filter('button.es-unlock[data-index="tag"]');
            $this->assertCount(1, $unlock);
            $unlockForm = $unlock->form();
            $this->assertStringEndsWith('/admin/populate-pass/unlock/tag', $unlockForm->getUri());

            // wrong token: nothing happens
            $this->client->request('POST', $unlockForm->getUri(), ['token' => 'invalid']);
            $this->assertResponseRedirects();
            $this->assertTrue($manager->isLocked('tag'));

            $this->client->request('POST', $unlockForm->getUri(), ['token' => $token]);
            $this->assertResponseRedirects();
            $this->assertFalse($manager->isLocked('tag'));
            $crawler = $this->client->followRedirect();
            $this->assertStringContainsString('Populate lock of "tag" released', $crawler->filter('#flash-messages')->text());

            // and the index can be queued again
            $this->client->request('POST', $form->getUri(), ['token' => $token, 'scope' => 'selected', 'indices' => ['tag']]);
            $this->assertResponseRedirects();
            $this->assertSame(['tag'], array_map(static fn (ESPopulate $m): ?string => $m->index, $this->getPopulateMessages()));
        } finally {
            $manager->forceRelease('tag');
        }
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
