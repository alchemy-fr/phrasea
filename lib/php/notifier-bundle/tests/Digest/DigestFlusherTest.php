<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Digest;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Channel\EmailChannel;
use Alchemy\NotifierBundle\Digest\DigestFlusher;
use Alchemy\NotifierBundle\Entity\NotificationPreference;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Manager\PreferenceManager;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use Alchemy\NotifierBundle\NotifierState;
use Alchemy\NotifierBundle\Repository\NotificationDigestRepository;
use Alchemy\NotifierBundle\Repository\NotificationPreferenceRepository;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class DigestFlusherTest extends TestCase
{
    private const string DIGEST_TEMPLATE = '@notifications/discussion/new_comment/email_digest.html.twig';
    private const string REGULAR_TEMPLATE = '@notifications/discussion/new_comment/email.html.twig';

    private MockClock $clock;

    /** @var array<int, Email> */
    private array $sent = [];

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-08-31 10:00:00');
        $this->sent = [];
    }

    public function testFlushesADueBucketAsOneDigestEmail(): void
    {
        $row = $this->row(3, [
            $this->event('Jane', 'obj-1'),
            $this->event('Bob', 'obj-1'),
            $this->event('Jane', 'obj-2'),
        ]);
        $repository = $this->repository($row, claimed: $row);

        $flusher = $this->flusher($repository);

        self::assertTrue($flusher->flushIfDue('digest-1'));
        self::assertCount(1, $this->sent);
        self::assertSame('3 comments', $this->sent[0]->getSubject());
        // 2 object groups, no overflow, recipient rebuilt from the subscriber
        self::assertSame('2/0/user@example.com', $this->sent[0]->getHtmlBody());
        self::assertSame('user@example.com', $this->sent[0]->getTo()[0]->getAddress());
    }

    public function testANotDueBucketIsLeftAlone(): void
    {
        $repository = $this->repository($this->row(2, [$this->event('Jane')]), claimed: null);

        self::assertFalse($this->flusher($repository)->flushIfDue('digest-1'));
        self::assertSame([], $this->sent);
    }

    public function testAMissingBucketIsDone(): void
    {
        $repository = $this->createMock(NotificationDigestRepository::class);
        $repository->method('findRow')->willReturn(null);
        $repository->expects(self::never())->method('claimIfDue');

        self::assertTrue($this->flusher($repository)->flushIfDue('digest-1'));
    }

    public function testASingleEventRendersTheRegularTemplate(): void
    {
        $row = $this->row(1, [$this->event('Jane')]);
        $flusher = $this->flusher($this->repository($row, claimed: $row));

        self::assertTrue($flusher->flushIfDue('digest-1'));
        self::assertCount(1, $this->sent);
        self::assertSame('From Jane', $this->sent[0]->getSubject());
    }

    public function testAMissingDigestTemplateSendsTheLatestEventOnly(): void
    {
        $row = $this->row(3, [$this->event('Jane'), $this->event('Bob')]);
        $flusher = $this->flusher($this->repository($row, claimed: $row), templates: [
            self::REGULAR_TEMPLATE => '{% block subject %}From {{ author }}{% endblock %}{% block body %}single{% endblock %}',
        ]);

        self::assertTrue($flusher->flushIfDue('digest-1'));
        self::assertCount(1, $this->sent);
        self::assertSame('From Bob', $this->sent[0]->getSubject());
    }

    public function testAGloballyDisabledStateDiscardsTheBucket(): void
    {
        $row = $this->row(2, [$this->event('Jane')]);
        $flusher = $this->flusher($this->repository($row, claimed: $row), enabled: false);

        self::assertTrue($flusher->flushIfDue('digest-1'));
        self::assertSame([], $this->sent);
    }

    public function testAnOptedOutSubscriberIsDiscarded(): void
    {
        $row = $this->row(2, [$this->event('Jane')]);
        $preference = new NotificationPreference(new Subscriber('user-1'), 'discussion:new_comment', ChannelType::Email, false);
        $flusher = $this->flusher($this->repository($row, claimed: $row), preference: $preference);

        self::assertTrue($flusher->flushIfDue('digest-1'));
        self::assertSame([], $this->sent);
    }

    public function testAVanishedSubscriberIsDiscarded(): void
    {
        $row = $this->row(2, [$this->event('Jane')]);
        $flusher = $this->flusher($this->repository($row, claimed: $row), subscriber: null);

        self::assertTrue($flusher->flushIfDue('digest-1'));
        self::assertSame([], $this->sent);
    }

    public function testTheRemainingDelayStopsAtTheInactivityBoundary(): void
    {
        // Quiet since 09:55 => due at 10:05; the 1h cap (09:30 + 1h) is later.
        $repository = $this->repository($this->row(2, [$this->event('Jane')], first: '2026-08-31 09:30:00', last: '2026-08-31 09:55:00'));

        self::assertSame(300, $this->flusher($repository)->getRemainingDelay('digest-1'));
    }

    public function testTheRemainingDelayIsCappedByTheMaxDelay(): void
    {
        // Still active (last event 09:59) but the bucket opened at 09:05:
        // the cap boundary (10:05) comes before the inactivity one (10:09).
        $repository = $this->repository($this->row(2, [$this->event('Jane')], first: '2026-08-31 09:05:00', last: '2026-08-31 09:59:00'));

        self::assertSame(300, $this->flusher($repository)->getRemainingDelay('digest-1'));
    }

    public function testTheRemainingDelayIsNullWhenTheBucketIsGone(): void
    {
        $repository = $this->createMock(NotificationDigestRepository::class);
        $repository->method('findRow')->willReturn(null);

        self::assertNull($this->flusher($repository)->getRemainingDelay('digest-1'));
    }

    /**
     * @param array<string, mixed>      $row
     * @param array<string, mixed>|null $claimed
     */
    private function repository(array $row, ?array $claimed = null): NotificationDigestRepository
    {
        $repository = $this->createMock(NotificationDigestRepository::class);
        $repository->method('findRow')->willReturn($row);
        $repository->method('claimIfDue')->willReturn($claimed);

        return $repository;
    }

    /**
     * @param array<string, string>|null $templates
     */
    private function flusher(
        NotificationDigestRepository $repository,
        ?array $templates = null,
        bool $enabled = true,
        ?Subscriber $subscriber = new Subscriber('user-1'),
        ?NotificationPreference $preference = null,
    ): DigestFlusher {
        $subscriber?->setEmail('user@example.com');

        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $fn) => $fn());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('find')->willReturn($subscriber);

        $topicRegistry = new TopicRegistry([
            'discussion:new_comment' => [
                'channels' => ['email', 'in_app'],
                'importance' => 'normal',
                'user_configurable' => true,
                'digest' => [
                    'inactivity_delay' => 600,
                    'max_delay' => 3600,
                    'channels' => ['email'],
                    'group_by' => 'objectId',
                ],
            ],
        ]);

        $preferenceRepository = $this->createMock(NotificationPreferenceRepository::class);
        $preferenceRepository->method('findOneForChannel')->willReturn($preference);
        $preferenceManager = new PreferenceManager($this->createMock(EntityManagerInterface::class), $preferenceRepository, $topicRegistry);

        $renderer = new NotificationRenderer(new Environment(new ArrayLoader($templates ?? [
            self::DIGEST_TEMPLATE => '{% block subject %}{{ count }} comments{% endblock %}{% block body %}{{ byObject|length }}/{{ overflowCount }}/{{ recipient.email }}{% endblock %}',
            self::REGULAR_TEMPLATE => '{% block subject %}From {{ author }}{% endblock %}{% block body %}single{% endblock %}',
        ])), '@notifications');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (RawMessage $message): void {
            \assert($message instanceof Email);
            $this->sent[] = $message;
        });

        return new DigestFlusher(
            $repository,
            $em,
            $topicRegistry,
            $preferenceManager,
            $renderer,
            new EmailChannel($mailer, $renderer),
            new NotifierState($enabled),
            $this->clock,
        );
    }

    /**
     * @return array{params: array<string, mixed>, at: string}
     */
    private function event(string $author, string $objectId = 'obj-1'): array
    {
        return [
            'params' => ['object' => 'Asset A', 'objectId' => $objectId, 'author' => $author],
            'at' => '2026-08-31T09:30:00+00:00',
        ];
    }

    /**
     * @param array<int, array{params: array<string, mixed>, at: string}> $events
     *
     * @return array<string, mixed>
     */
    private function row(int $count, array $events, string $first = '2026-08-31 09:30:00', string $last = '2026-08-31 09:45:00'): array
    {
        return [
            'id' => 'digest-1',
            'subscriber_id' => 'sub-1',
            'topic' => 'discussion:new_comment',
            'channel' => 'email',
            'events' => json_encode($events, JSON_THROW_ON_ERROR),
            'event_count' => $count,
            'first_event_at' => $first,
            'last_event_at' => $last,
            'created_at' => $first,
        ];
    }
}
