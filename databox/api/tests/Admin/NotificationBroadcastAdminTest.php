<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Entity\Broadcast;
use Alchemy\NotifierBundle\Message\BroadcastNotification;
use Alchemy\NotifierBundle\Subscriber\KeycloakUserDirectory;
use Alchemy\NotifierBundle\Topic\BuiltInTopic;
use Doctrine\ORM\EntityManagerInterface;

class NotificationBroadcastAdminTest extends AbstractAdminTest
{
    public function testBroadcastHistoryPageRenders(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');

        $this->client->request('GET', '/admin/broadcast');
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            echo $response->getContent();
        }
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBroadcastFormRenders(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');

        $crawler = $this->client->request('GET', '/admin/broadcast/new');
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            echo $response->getContent();
        }
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertCount(1, $crawler->filter('[name="Broadcast[subject]"]'));
        $this->assertCount(1, $crawler->filter('[name="Broadcast[body]"]'));
        $this->assertGreaterThan(0, $crawler->filter('[name="Broadcast[channels][]"]')->count());
    }

    public function testSubmittingQueuesABroadcast(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        // The kernel must survive the redirect, otherwise the intercepting
        // transport is rebuilt and forgets what was sent
        $this->client->disableReboot();

        /** @var TestTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.p2');
        $inMemory = $transport->intercept();

        $crawler = $this->client->request('GET', '/admin/broadcast/new');
        $form = $crawler->selectButton('Send')->form();

        $form['Broadcast[subject]'] = 'Maintenance';
        $form['Broadcast[body]'] = '<p>Tonight</p>';
        $form['Broadcast[url]'] = '/assets/42';

        // Checkbox groups are easier to override on the raw payload
        $values = $form->getPhpValues();
        $values['Broadcast']['channels'] = [ChannelType::Email->value];

        $this->client->request('POST', $form->getUri(), $values);
        $this->assertResponseRedirects();

        $messages = array_map(static fn ($envelope) => $envelope->getMessage(), $inMemory->getSent());
        $broadcasts = array_values(array_filter($messages, static fn ($m): bool => $m instanceof BroadcastNotification));

        $this->assertCount(1, $broadcasts);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $history = $em->getRepository(Broadcast::class)->find($broadcasts[0]->broadcastId);

        $this->assertNotNull($history, 'The broadcast was recorded in the history');
        $this->assertSame(BuiltInTopic::ADMIN_MESSAGE, $history->getTopic());
        $this->assertSame(KeycloakUserDirectory::NAME, $history->getDirectory());
        $this->assertSame(['email'], $history->getChannels());
        $this->assertSame('Maintenance', $history->getSubject());
        $this->assertSame('<p>Tonight</p>', $history->getBody());
        $this->assertSame('/assets/42', $history->getUrl());
        $this->assertSame(KeycloakClientTestMock::ADMIN_UID, $history->getInitiatorUserId());
        $this->assertSame(KeycloakClientTestMock::ADMIN_UID, $history->getExcludeUserId());
        // Delivery happens in the worker
        $this->assertNull($history->getCompletedAt());
    }

    public function testAnEmptyMessageIsRejected(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');

        $crawler = $this->client->request('GET', '/admin/broadcast/new');
        $crawler = $this->client->submit($crawler->selectButton('Send')->form());

        // EasyAdmin re-renders an invalid form with 422
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('should not be blank', $crawler->text());
    }
}
