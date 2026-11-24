<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Controller;

use Alchemy\NotifierBundle\Controller\NotificationPreferenceController;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Manager\PreferenceManager;
use Alchemy\NotifierBundle\Manager\SubscriberManager;
use Alchemy\NotifierBundle\Repository\NotificationPreferenceRepository;
use Alchemy\NotifierBundle\Security\CurrentSubscriberResolver;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class NotificationPreferenceControllerTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'enabled as string' => [['topic' => 'asset.comment', 'channel' => 'email', 'enabled' => 'false']];
        yield 'topic not a string' => [['items' => [['topic' => ['asset.comment'], 'channel' => 'email', 'enabled' => false]]]];
        yield 'entry not an object' => [['items' => ['asset.comment']]];
        yield 'unknown topic' => [['topic' => 'nope.nope', 'channel' => 'email', 'enabled' => false]];
        yield 'channel not delivered' => [['topic' => 'asset.comment', 'channel' => 'sms', 'enabled' => false]];
        yield 'too many items' => [['items' => array_fill(0, NotificationPreferenceController::MAX_ITEMS + 1, ['topic' => 'asset.comment', 'channel' => 'email', 'enabled' => false])]];
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testInvalidPayloadIsABadRequest(mixed $payload): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $this->expectException(BadRequestHttpException::class);
        $this->controller($em)->update($this->request($payload));
    }

    public function testConcurrentInsertIsAConflict(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new UniqueConstraintViolationException(
            new class('Duplicate key') extends AbstractException {},
            null,
        ));

        $this->expectException(ConflictHttpException::class);
        $this->controller($em)->update($this->request(['topic' => 'asset.comment', 'channel' => 'email', 'enabled' => false]));
    }

    private function controller(EntityManagerInterface $em): NotificationPreferenceController
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new InMemoryUser('11111111-1111-1111-1111-111111111111', null));

        $subscriberManager = $this->createStub(SubscriberManager::class);
        $subscriberManager->method('getOrCreate')->willReturn(new Subscriber('11111111-1111-1111-1111-111111111111'));

        $repository = $this->createStub(NotificationPreferenceRepository::class);
        $repository->method('findForSubscriber')->willReturn([]);

        $registry = new TopicRegistry([
            'asset.comment' => ['channels' => ['email', 'in_app'], 'importance' => 'normal', 'user_configurable' => true],
        ]);

        return new NotificationPreferenceController(
            new CurrentSubscriberResolver($security, $subscriberManager),
            new PreferenceManager($em, $repository, $registry),
        );
    }

    private function request(mixed $payload): Request
    {
        return Request::create('/notification-preferences', 'PUT', content: json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
