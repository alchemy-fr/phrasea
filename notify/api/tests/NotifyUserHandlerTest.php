<?php

declare(strict_types=1);

namespace App\Tests;

use App\Consumer\Handler\NotifyUserHandler;
use App\Contact\ContactManager;
use App\Entity\Contact;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use ColinODell\PsrTestLogger\TestLogger;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NotifyUserHandlerTest extends WebTestCase
{
    public function testNewNotifyOK(): void
    {
        $eventProducer = $this->createEventProducerMock();
        $contactManager = $this->createContactManagerMock();

        $handler = new NotifyUserHandler(
            $eventProducer,
            $contactManager
        );
        $logger = new TestLogger();
        $handler->setLogger($logger);

        $message = new EventMessage($handler::EVENT, [
            'user_id' => 'a_user_id',
            'template' => 'tpl',
            'contact_info' => [
                'email' => 'test@test.fr',
            ],
        ]);

        $handler->handle($message);

        $this->assertFalse($logger->hasErrorRecords());
    }

    public function testNotifyOK(): void
    {
        $eventProducer = $this->createEventProducerMock();
        $contactManager = $this->createContactManagerMock();

        $contact = new Contact();
        $contact->setUserId('a_user_id');
        $contact->setEmail('test@test.fr');

        $contactManager
            ->expects($this->once())
            ->method('getContact')
            ->willReturn($contact);

        $handler = new NotifyUserHandler(
            $eventProducer,
            $contactManager
        );
        $logger = new TestLogger();
        $handler->setLogger($logger);

        $message = new EventMessage($handler::EVENT, [
            'user_id' => 'a_user_id',
            'template' => 'tpl',
        ]);

        $handler->handle($message);
        $this->assertFalse($logger->hasErrorRecords());
    }

    public function testNotifyWithContactUpdateOK(): void
    {
        $eventProducer = $this->createEventProducerMock();
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

        $handler = new NotifyUserHandler(
            $eventProducer,
            $contactManager
        );
        $logger = new TestLogger();
        $handler->setLogger($logger);

        $message = new EventMessage($handler::EVENT, [
            'user_id' => 'a_user_id',
            'template' => 'tpl',
            'contact_info' => [
                'email' => 'new_email@test.fr',
            ],
        ]);

        $handler->handle($message);
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
     * @return EventProducer|MockObject
     */
    private function createEventProducerMock(): MockObject
    {
        /** @var EventProducer|MockObject $eventProducer */
        $eventProducer = $this->createMock(EventProducer::class);
        $eventProducer
            ->expects($this->once())
            ->method('publish');

        return $eventProducer;
    }
}
