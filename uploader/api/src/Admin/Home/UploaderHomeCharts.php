<?php

declare(strict_types=1);

namespace App\Admin\Home;

use Alchemy\AdminBundle\Home\DailyCounter;
use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeChartDataset;
use Alchemy\AdminBundle\Home\HomeStat;
use Alchemy\AdminBundle\Home\MimeTypeFamily;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\Target;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The charts of the admin home page.
 */
final readonly class UploaderHomeCharts
{
    public const string UPLOADS_TITLE = 'Uploads (30 days)';
    public const string UPLOADS_ICON = 'fa-solid fa-chart-column';
    public const int UPLOADS_WIDTH = HomeChart::WIDTH_FULL;
    public const string FILE_TYPE_TITLE = 'Uploaded assets by file type';
    public const string FILE_TYPE_ICON = 'fa-solid fa-chart-pie';
    public const int FILE_TYPE_WIDTH = HomeChart::WIDTH_SMALL;
    public const string TARGET_TITLE = 'Top targets (30 days)';
    public const string TARGET_ICON = 'fa-solid fa-bullseye';
    public const int TARGET_WIDTH = HomeChart::WIDTH_LARGE;

    private const int DAYS = 30;
    private const int TARGET_COUNT = 10;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * One query per series.
     */
    public function getUploadsChart(?string $url = null): HomeChart
    {
        $today = new \DateTimeImmutable();
        $qb = $this->em->getRepository(Commit::class)->createQueryBuilder('c');

        return $this->createUploadsChart(
            DailyCounter::getDays(self::DAYS, $today),
            DailyCounter::fromQueryBuilder($qb, 'c.createdAt', self::DAYS, today: $today),
            DailyCounter::fromQueryBuilder($qb, 'c.createdAt', self::DAYS, 'c.totalSize', $today),
            $url,
        );
    }

    public function getFileTypeChart(?string $url = null): HomeChart
    {
        /** @var list<array{mimeType: ?string, assetCount: int|string}> $rows */
        $rows = $this->em->getRepository(Asset::class)->createQueryBuilder('a')
            ->select('a.mimeType AS mimeType, COUNT(a.id) AS assetCount')
            ->groupBy('a.mimeType')
            ->getQuery()
            ->getArrayResult();

        return $this->createFileTypeChart($rows, $url);
    }

    public function getTargetChart(?string $url = null): HomeChart
    {
        /** @var list<array{targetId: string, commitCount: int|string, totalSize: int|string|null}> $rows */
        $rows = $this->em->getRepository(Commit::class)->createQueryBuilder('c')
            ->select('IDENTITY(c.target) AS targetId, COUNT(c.id) AS commitCount, COALESCE(SUM(c.totalSize), 0) AS totalSize')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('since', DailyCounter::getDays(self::DAYS)[0], Types::DATETIME_IMMUTABLE)
            ->groupBy('c.target')
            ->orderBy('commitCount', 'DESC')
            ->setMaxResults(self::TARGET_COUNT)
            ->getQuery()
            ->getArrayResult();

        $names = [];
        $ids = array_map(static fn (array $row): string => (string) $row['targetId'], $rows);
        if ([] !== $ids) {
            /** @var Target[] $targets */
            $targets = $this->em->getRepository(Target::class)->findBy(['id' => $ids]);
            foreach ($targets as $target) {
                $names[$target->getId()] = (string) $target->getName();
            }
        }

        return $this->createTargetChart($rows, $names, $url);
    }

    /**
     * Bars for the commits, a line for their volume.
     *
     * @param list<\DateTimeImmutable> $days
     * @param array<string, int>       $commits indexed by day ("Y-m-d")
     * @param array<string, int>       $volumes indexed by day ("Y-m-d"), in bytes
     */
    public function createUploadsChart(array $days, array $commits, array $volumes, ?string $url = null): HomeChart
    {
        $commitData = [];
        $volumeData = [];
        foreach ($days as $day) {
            $key = $day->format('Y-m-d');
            $commitData[] = $commits[$key] ?? 0;
            $volumeData[] = $volumes[$key] ?? 0;
        }

        return new HomeChart(
            title: self::UPLOADS_TITLE,
            icon: self::UPLOADS_ICON,
            type: HomeChart::TYPE_BAR,
            labels: array_map(DailyCounter::formatLabel(...), $days),
            datasets: [
                new HomeChartDataset('Commits', $commitData),
                new HomeChartDataset('Volume', $volumeData, HomeStat::FORMAT_BYTES, 'y1', HomeChart::TYPE_LINE),
            ],
            url: $url,
            width: self::UPLOADS_WIDTH,
        );
    }

    /**
     * @param list<array{mimeType: ?string, assetCount: int|string}> $rows
     */
    public function createFileTypeChart(array $rows, ?string $url = null): HomeChart
    {
        $counts = [];
        foreach ($rows as $row) {
            $mimeType = (string) $row['mimeType'];
            $counts[$mimeType] = ($counts[$mimeType] ?? 0) + (int) $row['assetCount'];
        }
        $families = MimeTypeFamily::aggregate($counts);

        return new HomeChart(
            title: self::FILE_TYPE_TITLE,
            icon: self::FILE_TYPE_ICON,
            type: HomeChart::TYPE_DOUGHNUT,
            labels: array_keys($families),
            datasets: [new HomeChartDataset('Assets', array_values($families))],
            url: $url,
            width: self::FILE_TYPE_WIDTH,
        );
    }

    /**
     * @param list<array{targetId: string, commitCount: int|string, totalSize: int|string|null}> $rows
     * @param array<string, string>                                                              $names indexed by target ID, the unknown
     *                                                                                                  or unnamed ones being labelled with their ID
     */
    public function createTargetChart(array $rows, array $names, ?string $url = null): HomeChart
    {
        $labels = [];
        $counts = [];
        $sizes = [];
        foreach ($rows as $row) {
            $id = (string) $row['targetId'];
            $name = $names[$id] ?? '';
            $labels[] = '' !== $name ? $name : $id;
            $counts[] = (int) $row['commitCount'];
            $sizes[] = (int) ($row['totalSize'] ?? 0);
        }

        return new HomeChart(
            title: self::TARGET_TITLE,
            icon: self::TARGET_ICON,
            type: HomeChart::TYPE_BAR,
            labels: $labels,
            datasets: [
                new HomeChartDataset('Commits', $counts),
                new HomeChartDataset('Volume', $sizes, HomeStat::FORMAT_BYTES, 'x1'),
            ],
            horizontal: true,
            url: $url,
            width: self::TARGET_WIDTH,
        );
    }
}
