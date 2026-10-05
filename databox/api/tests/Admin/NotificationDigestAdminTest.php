<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Repository\NotificationDigestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

class NotificationDigestAdminTest extends AbstractAdminTest
{
    public function testPendingDigestsPageRenders(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');

        $this->client->request('GET', '/admin/notification-digest');
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            echo $response->getContent();
        }
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testABufferedDigestIsListed(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // The test database survives across tests: a fresh userId keeps the
        // unique (subscriber, topic, channel) bucket free of leftovers
        $subscriber = new Subscriber(Uuid::uuid4()->toString());
        $subscriber->setEmail('digest-admin-test@phrasea.local');
        $em->persist($subscriber);
        $em->flush();

        /** @var NotificationDigestRepository $repository */
        $repository = $container->get(NotificationDigestRepository::class);
        $now = new \DateTimeImmutable();
        // Two appends: the second one exercises the upsert conflict path
        $first = $repository->append($subscriber->getId(), 'discussion:new_comment', 'email', [
            'params' => ['object' => 'Asset A', 'objectId' => 'obj-1', 'author' => 'Jane'],
            'at' => $now->format(\DateTimeInterface::ATOM),
        ], $now);
        $second = $repository->append($subscriber->getId(), 'discussion:new_comment', 'email', [
            'params' => ['object' => 'Asset A', 'objectId' => 'obj-1', 'author' => 'Bob'],
            'at' => $now->format(\DateTimeInterface::ATOM),
        ], $now);

        $this->assertTrue($first['inserted']);
        $this->assertFalse($second['inserted']);
        $this->assertSame($first['id'], $second['id']);

        $row = $repository->findRow($first['id']);
        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['event_count']);
        $this->assertCount(2, json_decode((string) $row['events'], true));

        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        $crawler = $this->client->request('GET', '/admin/notification-digest');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('discussion:new_comment', $crawler->text());
    }

    public function testReadingInAppNotificationsDiscardsTheBuckets(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $subscriber = new Subscriber(Uuid::uuid4()->toString());
        $em->persist($subscriber);
        $em->flush();

        /** @var NotificationDigestRepository $repository */
        $repository = $container->get(NotificationDigestRepository::class);
        $now = new \DateTimeImmutable();
        $event = ['params' => ['objectId' => 'obj-1'], 'at' => $now->format(\DateTimeInterface::ATOM)];
        $comment = $repository->append($subscriber->getId(), 'discussion:new_comment', 'email', $event, $now);
        $update = $repository->append($subscriber->getId(), 'asset:update', 'email', $event, $now);

        // Reading a notification of one topic only drops that topic's bucket
        $this->assertSame(1, $repository->deleteBucketFor($subscriber->getId(), 'discussion:new_comment'));
        $this->assertNull($repository->findRow($comment['id']));
        $this->assertNotNull($repository->findRow($update['id']));

        // Read-all drops everything left
        $this->assertSame(1, $repository->deleteAllFor($subscriber->getId()));
        $this->assertNull($repository->findRow($update['id']));
    }
}
