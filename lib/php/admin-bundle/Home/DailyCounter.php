<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Home;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;

/**
 * Counts objects per day over the last days, for the time series of the admin home page.
 */
final class DailyCounter
{
    public const int DEFAULT_DAYS = 30;

    /**
     * Counts, in a single query, the rows of $qb (the root alias being the counted entity)
     * whose $dateField falls in each of the last $days days, today included.
     *
     * The days are compared in SQL as date ranges (one SUM(CASE…) column per day), so that
     * no date function is needed and the query runs the same on every database platform.
     *
     * @param string  $dateField e.g. "c.createdAt"
     * @param ?string $sumExpr   a value to sum instead of counting the rows, e.g. "c.totalSize"
     *
     * @return array<string, int> indexed by day ("Y-m-d"), from the oldest one to today, the days
     *                            without any row counting zero
     */
    public static function fromQueryBuilder(QueryBuilder $qb, string $dateField, int $days = self::DEFAULT_DAYS, ?string $sumExpr = null, ?\DateTimeImmutable $today = null): array
    {
        $dayList = self::getDays($days, $today);

        $qb = (clone $qb)->resetDQLPart('orderBy');
        $value = $sumExpr ?? '1';

        $bounds = [...$dayList, $dayList[count($dayList) - 1]->modify('+1 day')];
        foreach ($bounds as $i => $bound) {
            $qb->setParameter('homeDay'.$i, $bound, Types::DATETIME_IMMUTABLE);
        }
        $columns = [];
        foreach (array_keys($dayList) as $i) {
            $columns[] = sprintf(
                'COALESCE(SUM(CASE WHEN %1$s >= :homeDay%2$d AND %1$s < :homeDay%3$d THEN %4$s ELSE 0 END), 0) AS homeDay%2$dCount',
                $dateField,
                $i,
                $i + 1,
                $value,
            );
        }
        $qb->select(...$columns);
        $qb->andWhere(sprintf('%s >= :homeDay0', $dateField));

        $row = $qb->getQuery()->getSingleResult();

        $counts = [];
        foreach ($dayList as $i => $day) {
            $counts[$day->format('Y-m-d')] = (int) $row['homeDay'.$i.'Count'];
        }

        return $counts;
    }

    /**
     * The start (midnight) of each of the last $days days, from the oldest one to today.
     *
     * @return list<\DateTimeImmutable>
     */
    public static function getDays(int $days = self::DEFAULT_DAYS, ?\DateTimeImmutable $today = null): array
    {
        if ($days < 1) {
            throw new \InvalidArgumentException(sprintf('Cannot count over %d days', $days));
        }

        $today = ($today ?? new \DateTimeImmutable())->setTime(0, 0);

        $list = [];
        for ($i = $days - 1; $i >= 0; --$i) {
            $list[] = $today->modify(sprintf('-%d days', $i));
        }

        return $list;
    }

    public static function formatLabel(\DateTimeInterface $day): string
    {
        return $day->format('d/m');
    }
}
