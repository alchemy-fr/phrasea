<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Alchemy\ConfiguratorBundle\Message\DeployConfig;
use Alchemy\MessengerBundle\Entity\MessengerMessage;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Doctrine\DBAL\Connection;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MessengerMessageAdminTest extends AbstractDataboxAdminTestCase
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

    public function testRetryAllRetriesEveryPendingMessage(): void
    {
        $ids = $this->createFailedMessages(4);
        $this->markAsRetried($ids[1]);

        $form = $this->loadIndexPage()->filter('form[action*="retry-all"]');
        $this->assertCount(1, $form, 'the global "Retry all" action is on the list page');

        $this->client->request('POST', $form->attr('action'));
        $this->assertResponseRedirects();
        // read before following the redirect: the next request resets the in-memory transport
        $envelopes = $this->getDispatchedEnvelopes();
        $this->assertCount(3, $envelopes);
        foreach ($envelopes as $envelope) {
            $this->assertInstanceOf(DeployConfig::class, $envelope->getMessage());
            foreach ([RedeliveryStamp::class, SentToFailureTransportStamp::class, ErrorDetailsStamp::class, DelayStamp::class] as $stampClass) {
                $this->assertSame([], $envelope->all($stampClass), sprintf('%s is removed', $stampClass));
            }
        }
        $this->assertSame([], $this->getPendingIds());

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('3 message(s) retried', $crawler->filter('#flash-messages')->text());
    }

    public function testRetryAllWithoutPendingMessage(): void
    {
        $form = $this->loadIndexPage()->filter('form[action*="retry-all"]');

        $this->client->request('POST', $form->attr('action'));
        $this->assertResponseRedirects();
        $this->assertSame([], $this->getDispatchedEnvelopes());

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('No message to retry', $crawler->filter('#flash-messages')->text());
    }

    public function testRetryAllRequiresPost(): void
    {
        $this->createFailedMessages(1);
        $form = $this->loadIndexPage()->filter('form[action*="retry-all"]');

        $this->client->request('GET', $form->attr('action'));
        // a GET falls through to the detail route ("/{entityId}"), which finds no such message
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame([], $this->getDispatchedEnvelopes());
        $this->assertCount(1, $this->getPendingIds());
    }

    public function testBatchRetryRetriesSelectedMessages(): void
    {
        $ids = $this->createFailedMessages(4);
        $this->markAsRetried($ids[2]);

        $button = $this->getBatchRetryButton();
        $this->client->request('POST', $button->attr('data-action-url'), [
            'batchActionName' => 'batchRetry',
            'entityFqcn' => MessengerMessage::class,
            'batchActionEntityIds' => [$ids[0], $ids[1], $ids[2]],
            'batchActionCsrfToken' => $button->attr('data-action-csrf-token'),
        ]);
        $this->assertResponseRedirects();
        $this->assertCount(2, $this->getDispatchedEnvelopes());
        $this->assertSame([$ids[3]], $this->getPendingIds());

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('2 message(s) retried, 1 already retried', $crawler->filter('#flash-messages')->text());
    }

    public function testBatchRetryRejectsInvalidCsrfToken(): void
    {
        $ids = $this->createFailedMessages(2);

        $button = $this->getBatchRetryButton();
        $this->client->request('POST', $button->attr('data-action-url'), [
            'batchActionName' => 'batchRetry',
            'entityFqcn' => MessengerMessage::class,
            'batchActionEntityIds' => $ids,
            'batchActionCsrfToken' => 'invalid',
        ]);
        $this->assertResponseRedirects();
        $this->assertSame([], $this->getDispatchedEnvelopes());
        $this->assertSame($ids, $this->getPendingIds());

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Invalid CSRF token', $crawler->filter('#flash-messages')->text());
    }

    public function testBatchRetryRejectsAnotherEntity(): void
    {
        $ids = $this->createFailedMessages(1);

        $button = $this->getBatchRetryButton();
        $this->client->request('POST', $button->attr('data-action-url'), [
            'batchActionName' => 'batchRetry',
            'entityFqcn' => \stdClass::class,
            'batchActionEntityIds' => $ids,
            'batchActionCsrfToken' => $button->attr('data-action-csrf-token'),
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertSame([], $this->getDispatchedEnvelopes());
        $this->assertSame($ids, $this->getPendingIds());
    }

    public function testRetryingAnAlreadyRetriedMessageDoesNotDispatchAgain(): void
    {
        $this->createFailedMessages(1);

        $link = $this->loadIndexPage()->filter('a.action-retry');
        $this->assertCount(1, $link);
        $url = $link->attr('href');

        $this->client->request('GET', $url);
        $this->assertResponseRedirects();
        $this->assertCount(1, $this->getDispatchedEnvelopes());
        $this->assertSame([], $this->getPendingIds());
        $crawler = $this->client->followRedirect();
        $this->assertCount(0, $crawler->filter('a.action-retry'), 'a retried message has no "Retry" action any more');

        $this->client->request('GET', $url);
        $this->assertResponseRedirects();
        $this->assertSame([], $this->getDispatchedEnvelopes());
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Message already retried', $crawler->filter('#flash-messages')->text());
    }

    private function loadIndexPage(): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/messenger-message');
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            echo $response->getContent();
        }
        $this->assertEquals(200, $response->getStatusCode());

        return $crawler;
    }

    private function getBatchRetryButton(): Crawler
    {
        $button = $this->loadIndexPage()->filter('[data-action-name="batchRetry"]');
        $this->assertCount(1, $button, 'the "Retry" batch action is on the list page');

        return $button;
    }

    /**
     * Sends the messages through the failure transport, as the workers do.
     *
     * @return list<int>
     */
    private function createFailedMessages(int $count): array
    {
        /** @var TransportInterface $failed */
        $failed = static::getContainer()->get('messenger.transport.failed');
        for ($i = 0; $i < $count; ++$i) {
            $failed->send(new Envelope(new DeployConfig(), [
                new SentToFailureTransportStamp('p2'),
                new RedeliveryStamp(3),
                ErrorDetailsStamp::create(new \RuntimeException('boom')),
            ]));
        }

        return array_map('intval', $this->getConnection()->fetchFirstColumn('SELECT id FROM messenger_messages ORDER BY id'));
    }

    private function markAsRetried(int $id): void
    {
        $this->getConnection()->executeStatement('UPDATE messenger_messages SET delivered_at = :at WHERE id = :id', [
            'at' => '9999-12-31 23:59:59',
            'id' => $id,
        ]);
    }

    /**
     * @return list<int>
     */
    private function getPendingIds(): array
    {
        return array_map('intval', $this->getConnection()->fetchFirstColumn('SELECT id FROM messenger_messages WHERE delivered_at IS NULL ORDER BY id'));
    }

    /**
     * @return list<Envelope>
     */
    private function getDispatchedEnvelopes(): array
    {
        return array_values(array_filter(
            [...$this->inMemory->getSent()],
            static fn (Envelope $envelope): bool => $envelope->getMessage() instanceof DeployConfig,
        ));
    }

    private function getConnection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
