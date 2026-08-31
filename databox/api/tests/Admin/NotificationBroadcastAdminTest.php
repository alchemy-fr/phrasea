<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Controller\Admin\BroadcastNotificationController;
use Alchemy\NotifierBundle\Message\BroadcastNotification;
use Alchemy\NotifierBundle\Subscriber\KeycloakUserDirectory;
use Alchemy\NotifierBundle\Topic\BuiltInTopic;

class NotificationBroadcastAdminTest extends AbstractAdminTest
{
    public function testBroadcastPageRenders(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');

        $crawler = $this->client->request('GET', '/admin/notifications/broadcast');
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            echo $response->getContent();
        }
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertCount(1, $crawler->filter('[name="broadcast_message[subject]"]'));
        $this->assertCount(1, $crawler->filter('[name="broadcast_message[body]"]'));
        $this->assertGreaterThan(0, $crawler->filter('[name="broadcast_message[channels][]"]')->count());
        $this->assertSame('notifier_broadcast', BroadcastNotificationController::ROUTE_NAME);
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

        $crawler = $this->client->request('GET', '/admin/notifications/broadcast');
        $form = $crawler->selectButton('Send')->form();

        $form['broadcast_message[subject]'] = 'Maintenance';
        $form['broadcast_message[body]'] = '<p>Tonight</p>';
        $form['broadcast_message[url]'] = '/assets/42';

        // Checkbox groups are easier to override on the raw payload
        $values = $form->getPhpValues();
        $values['broadcast_message']['channels'] = [ChannelType::Email->value];

        $this->client->request('POST', $form->getUri(), $values);
        $this->assertResponseRedirects();

        $messages = array_map(static fn ($envelope) => $envelope->getMessage(), $inMemory->getSent());
        $broadcasts = array_values(array_filter($messages, static fn ($m): bool => $m instanceof BroadcastNotification));

        $this->assertCount(1, $broadcasts);

        $broadcast = $broadcasts[0];
        $this->assertSame(BuiltInTopic::ADMIN_MESSAGE, $broadcast->topic);
        $this->assertSame('Maintenance', $broadcast->params['subject']);
        $this->assertSame('<p>Tonight</p>', $broadcast->params['body']);
        $this->assertSame('/assets/42', $broadcast->params['url']);
        $this->assertSame(['email'], $broadcast->channels);
        $this->assertSame(KeycloakUserDirectory::NAME, $broadcast->directory);
    }

    public function testAnEmptyMessageIsRejected(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');

        $crawler = $this->client->request('GET', '/admin/notifications/broadcast');
        $crawler = $this->client->submit($crawler->selectButton('Send')->form());

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Fill in both the subject and the message', $crawler->text());
    }
}
