<?php

declare(strict_types=1);

namespace App\Tests;

use App\Consumer\Handler\NotifyUser;
use App\Consumer\Handler\NotifyUserHandler;
use App\Contact\ContactManager;
use App\Entity\Contact;
use ColinODell\PsrTestLogger\TestLogger;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class NotifyUserHandlerTest extends WebTestCase
{
    public function testNewNotifyOK(): void
    {
        $bus = $this->createBusMock();
        $contactManager = $this->createContactManagerMock();

        $logger = new TestLogger();
        $handler = new NotifyUserHandler(
            $bus,
            $contactManager,
            $logger,
        );

        $message = new NotifyUser(
            'a_user_id',
            'tpl',
            [],
            [
                'email' => 'test@test.fr',
            ],
        );

        $handler($message);

        $this->assertFalse($logger->hasErrorRecords());
    }

    public function testNotifyOK(): void
    {
        $bus = $this->createBusMock();
        $contactManager = $this->createContactManagerMock();

        $contact = new Contact();
        $contact->setUserId('a_user_id');
        $contact->setEmail('test@test.fr');

        $contactManager
            ->expects($this->once())
            ->method('getContact')
            ->willReturn($contact);

        $logger = new TestLogger();
        $handler = new NotifyUserHandler(
            $bus,
            $contactManager,
            $logger,
        );

        $message = new NotifyUser('a_user_id', 'tpl');

        $handler($message);
        $this->assertFalse($logger->hasErrorRecords());
    }

    public function testNotifyWithContactUpdateOK(): void
    {
        $bus = $this->createBusMock();
        $contactManager = $this->createContactManagerMock();

        $contact = new Contact();
        $contact->setUserId('a_user_id');
        $contact->setEmail('test@test.fr');

        $contactManager
            ->expects($this->once())
            ->method('getContact')
            ->willReturn($contact);

        $contactManager
            ->expects($this->once())
            ->method('updateContact')
            ->with(
                $this->equalTo($contact),
                $this->equalTo(['email' => 'new_email@test.fr'])
            );

        $logger = new TestLogger();
        $handler = new NotifyUserHandler(
            $bus,
            $contactManager,
            $logger
        );

        $message = new NotifyUser(
            'a_user_id',
            'tpl',
            [],
            [
                'email' => 'new_email@test.fr',
            ],
        );

        $handler($message);
        $this->assertFalse($logger->hasErrorRecords());
    }

    /**
     * @return ContactManager|MockObject
     */
    private function createContactManagerMock(): MockObject
    {
        /** @var ContactManager $contactManager */
        $contactManager = $this->createMock(ContactManager::class);
        $contactManager
            ->method('createContact')
            ->willReturnCallback(function (string $userId, array $data): Contact {
                $contact = new Contact();
                $contact->setUserId($userId);
                $contact->setEmail($data['email']);

                return $contact;
            });

        return $contactManager;
    }

    /**
     * @return MessageBusInterface|MockObject
     */
    private function createBusMock(): MockObject
    {
        /** @var MessageBusInterface|MockObject $bus */
        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) {
                return new Envelope($message, []);
            })
        ;

        return $bus;
    }
}
