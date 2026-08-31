<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Repository;

use Alchemy\NotifierBundle\Entity\NotificationDigest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * Raw-SQL access to the digest buffer.
 *
 * The buffer is written by concurrent workers, so both mutations are single
 * atomic statements: the append is an upsert on the bucket unique index, and
 * the flush claims the whole bucket with a conditional DELETE ... RETURNING —
 * exactly one caller gets the events, a retried flush finds nothing.
 *
 * Production runs on PostgreSQL; the JSON-append expression also has an SQLite
 * variant because the applications run their test suites on SQLite.
 *
 * @extends ServiceEntityRepository<NotificationDigest>
 */
class NotificationDigestRepository extends ServiceEntityRepository
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDigest::class);
    }

    /**
     * Adds one event to the (subscriber, topic, channel) bucket, creating it if
     * needed. Past {@see NotificationDigest::MAX_EVENTS} only the counters move.
     *
     * @param array{params: array<string, mixed>, at: string} $event
     *
     * @return array{id: string, inserted: bool} inserted tells whether this call created the bucket
     */
    public function append(string $subscriberId, string $topic, string $channel, array $event, \DateTimeImmutable $now): array
    {
        $connection = $this->getEntityManager()->getConnection();

        // The appended element only differs in how each platform grows the array
        $appendExpression = $connection->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? '(notifier_digest.events::jsonb || jsonb_build_array(:event::jsonb))::json'
            : "json_insert(notifier_digest.events, '$[#]', json(:event))";

        $sql = <<<SQL
            INSERT INTO notifier_digest (id, subscriber_id, topic, channel, events, event_count, first_event_at, last_event_at, created_at)
            VALUES (:id, :subscriberId, :topic, :channel, :events, 1, :now, :now, :now)
            ON CONFLICT (subscriber_id, topic, channel) DO UPDATE SET
                events = CASE WHEN notifier_digest.event_count < :cap
                    THEN {$appendExpression}
                    ELSE notifier_digest.events END,
                event_count = notifier_digest.event_count + 1,
                last_event_at = excluded.last_event_at
            RETURNING id, event_count
            SQL;

        $row = $connection->fetchAssociative($sql, [
            'id' => Uuid::uuid4()->toString(),
            'subscriberId' => $subscriberId,
            'topic' => $topic,
            'channel' => $channel,
            'events' => json_encode([$event], JSON_THROW_ON_ERROR),
            'event' => json_encode($event, JSON_THROW_ON_ERROR),
            'now' => $now->format(self::DATE_FORMAT),
            'cap' => NotificationDigest::MAX_EVENTS,
        ]);
        \assert(false !== $row);

        return [
            'id' => (string) $row['id'],
            // The insert seeds the counter at 1; any conflicting append moves it past that
            'inserted' => 1 === (int) $row['event_count'],
        ];
    }

    /**
     * @return array<string, mixed>|null the raw row, or null when the bucket no longer exists
     */
    public function findRow(string $id): ?array
    {
        $row = $this->getEntityManager()->getConnection()
            ->fetchAssociative('SELECT * FROM notifier_digest WHERE id = :id', ['id' => $id]);

        return false !== $row ? $row : null;
    }

    /**
     * @return array<int, string> ids of every open bucket
     */
    public function findAllIds(): array
    {
        return $this->getEntityManager()->getConnection()
            ->fetchFirstColumn('SELECT id FROM notifier_digest ORDER BY last_event_at');
    }

    /**
     * Atomically claims the bucket when its window has elapsed (quiet for
     * $inactivityDelay seconds, or opened more than $maxDelay seconds ago),
     * removing the row and returning its content.
     *
     * @return array<string, mixed>|null null when not due, or already claimed by a concurrent flush
     */
    public function claimIfDue(string $id, \DateTimeImmutable $now, int $inactivityDelay, int $maxDelay, bool $force = false): ?array
    {
        $sql = $force
            ? 'DELETE FROM notifier_digest WHERE id = :id RETURNING *'
            : <<<'SQL'
                DELETE FROM notifier_digest
                WHERE id = :id
                  AND (last_event_at <= :inactivityThreshold OR first_event_at <= :maxThreshold)
                RETURNING *
                SQL;

        $params = ['id' => $id];
        if (!$force) {
            $params['inactivityThreshold'] = $now->modify(sprintf('-%d seconds', $inactivityDelay))->format(self::DATE_FORMAT);
            $params['maxThreshold'] = $now->modify(sprintf('-%d seconds', $maxDelay))->format(self::DATE_FORMAT);
        }

        $row = $this->getEntityManager()->getConnection()->fetchAssociative($sql, $params);

        return false !== $row ? $row : null;
    }
}
