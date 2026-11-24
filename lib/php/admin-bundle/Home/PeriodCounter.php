<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Home;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;

/**
 * Counts objects over the sliding windows of the admin home page.
 */
final class PeriodCounter
{
    public const array PERIODS = [
        'last24h' => '-24 hours',
        'last7d' => '-7 days',
        'last30d' => '-30 days',
    ];

    /**
     * Counts, in a single query, the rows of $qb (the root alias being the counted entity)
     * in total and whose $dateField falls in each window.
     *
     * @param string  $dateField e.g. "c.createdAt"
     * @param ?string $sumExpr   a value to sum instead of counting the rows, e.g. "c.totalSize"
     *
     * @return array{total: int, last24h: int, last7d: int, last30d: int}
     */
    public static function fromQueryBuilder(QueryBuilder $qb, string $dateField, ?string $sumExpr = null, ?\DateTimeImmutable $now = null): array
    {
        $qb = (clone $qb)->resetDQLPart('orderBy');
        $value = $sumExpr ?? '1';
        $qb->select(null !== $sumExpr
            ? sprintf('COALESCE(SUM(%s), 0) AS total', $sumExpr)
            : sprintf('COUNT(%s) AS total', $qb->getRootAliases()[0]));

        foreach (self::getBounds($now) as $period => $since) {
            $param = 'homeSince'.$period;
            $qb->addSelect(sprintf('COALESCE(SUM(CASE WHEN %s >= :%s THEN %s ELSE 0 END), 0) AS %s', $dateField, $param, $value, $period));
            $qb->setParameter($param, $since, Types::DATETIME_IMMUTABLE);
        }

        $row = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int) $row['total'],
            'last24h' => (int) $row['last24h'],
            'last7d' => (int) $row['last7d'],
            'last30d' => (int) $row['last30d'],
        ];
    }

    /**
     * For the sources that cannot count the windows in one go (Elasticsearch, distinct counts…).
     *
     * @param callable(?\DateTimeImmutable $since): int $count called with null for the total
     *
     * @return array{total: int, last24h: int, last7d: int, last30d: int}
     */
    public static function fromCallable(callable $count, ?\DateTimeImmutable $now = null): array
    {
        $counts = ['total' => $count(null)];
        foreach (self::getBounds($now) as $period => $since) {
            $counts[$period] = $count($since);
        }

        return $counts;
    }

    /**
     * @return array{last24h: \DateTimeImmutable, last7d: \DateTimeImmutable, last30d: \DateTimeImmutable}
     */
    public static function getBounds(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return array_map(static fn (string $modifier): \DateTimeImmutable => $now->modify($modifier), self::PERIODS);
    }
}
