<?php

declare(strict_types=1);

namespace Alchemy\MessengerBundle\Retry;

use Alchemy\MessengerBundle\Entity\MessengerMessage;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Re-dispatches messages of the failure transport. A retried row is kept in the table and
 * marked with a far future "deliveredAt" (RETRIED_AT), so that the transport never hands it
 * out again and the admin shows it as retried.
 */
final readonly class FailedMessageRetrier
{
    public const string RETRIED_AT = '9999-12-31 23:59:59';
    private const int BATCH_SIZE = 100;

    public function __construct(
        private MessageBusInterface $bus,
        private SerializerInterface $messengerSerializer,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, mixed> $headers
     *
     * @return bool false when the message was already retried (nothing is dispatched)
     */
    public function retry(string $id, string $body, array $headers): bool
    {
        // Claiming the row first makes a concurrent retry (another admin, a double click) a no-op
        $claimed = $this->em->createQuery(sprintf('UPDATE %s m SET m.deliveredAt = :retried WHERE m.id = :id AND m.deliveredAt IS NULL', MessengerMessage::class))
            ->setParameter('retried', new \DateTimeImmutable(self::RETRIED_AT), Types::DATETIME_IMMUTABLE)
            ->setParameter('id', (int) $id)
            ->execute();

        if (0 === $claimed) {
            return false;
        }

        try {
            $envelope = $this->messengerSerializer->decode([
                'body' => $body,
                'headers' => $headers,
            ]);

            $this->bus->dispatch($envelope
                ->withoutAll(DelayStamp::class)
                ->withoutAll(RedeliveryStamp::class)
                ->withoutAll(SentToFailureTransportStamp::class)
                ->withoutAll(ErrorDetailsStamp::class));
        } catch (\Throwable $e) {
            $this->em->createQuery(sprintf('UPDATE %s m SET m.deliveredAt = NULL WHERE m.id = :id', MessengerMessage::class))
                ->setParameter('id', (int) $id)
                ->execute();

            throw $e;
        }

        return true;
    }

    /**
     * Retries every message that was not retried yet.
     */
    public function retryAll(): RetryReport
    {
        $maxId = $this->em->createQuery(sprintf('SELECT MAX(m.id) FROM %s m WHERE m.deliveredAt IS NULL', MessengerMessage::class))
            ->getSingleScalarResult();

        if (null === $maxId) {
            return new RetryReport();
        }

        // The upper bound is frozen: a retried message that fails again comes back
        // with a greater id while we iterate, and must not be retried in the same run.
        return $this->retryRows($this->iteratePendingRows((int) $maxId));
    }

    /**
     * @param array<mixed> $ids
     */
    public function retryIds(array $ids): RetryReport
    {
        $ids = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter($ids, static fn (mixed $id): bool => is_numeric($id)),
        )));

        return $this->retryRows($this->iterateRowsByIds($ids));
    }

    /**
     * @param iterable<array{id: int|string, body: string, headers: string}> $rows
     */
    private function retryRows(iterable $rows): RetryReport
    {
        $retried = $skipped = $failed = 0;
        $firstError = null;

        foreach ($rows as $row) {
            try {
                $headers = json_decode($row['headers'], true, 512, JSON_THROW_ON_ERROR);
                if ($this->retry((string) $row['id'], $row['body'], $headers)) {
                    ++$retried;
                } else {
                    ++$skipped;
                }
            } catch (TransportException $e) {
                // The broker is unreachable: every following message would fail the same way
                return new RetryReport($retried, $skipped, $failed, $firstError, $e->getMessage());
            } catch (\Throwable $e) {
                ++$failed;
                $firstError ??= $e->getMessage();
            }
        }

        return new RetryReport($retried, $skipped, $failed, $firstError);
    }

    /**
     * @return \Generator<array{id: int|string, body: string, headers: string}>
     */
    private function iteratePendingRows(int $maxId): \Generator
    {
        $lastId = 0;
        do {
            $rows = $this->em->createQuery(sprintf(
                'SELECT m.id, m.body, m.headers FROM %s m WHERE m.deliveredAt IS NULL AND m.id > :last AND m.id <= :max ORDER BY m.id ASC',
                MessengerMessage::class,
            ))
                ->setParameter('last', $lastId)
                ->setParameter('max', $maxId)
                ->setMaxResults(self::BATCH_SIZE)
                ->getArrayResult();

            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                yield $row;
            }

            $this->em->clear();
        } while (self::BATCH_SIZE === count($rows));
    }

    /**
     * @param list<int> $ids
     *
     * @return \Generator<array{id: int|string, body: string, headers: string}>
     */
    private function iterateRowsByIds(array $ids): \Generator
    {
        sort($ids);
        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            $rows = $this->em->createQuery(sprintf(
                'SELECT m.id, m.body, m.headers FROM %s m WHERE m.id IN (:ids) ORDER BY m.id ASC',
                MessengerMessage::class,
            ))
                ->setParameter('ids', $chunk, ArrayParameterType::INTEGER)
                ->getArrayResult();

            yield from $rows;

            $this->em->clear();
        }
    }
}
