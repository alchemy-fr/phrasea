<?php

declare(strict_types=1);

namespace App\Admin\Home;

use Alchemy\AdminBundle\Home\DailyCounter;
use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeChartDataset;
use Alchemy\AdminBundle\Home\HomeChartFactory;
use Alchemy\AdminBundle\Home\HomeStat;
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
        private HomeChartFactory $chartFactory,
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
        return $this->chartFactory->createFileTypeChart(
            self::FILE_TYPE_TITLE,
            self::FILE_TYPE_ICON,
            $this->chartFactory->countByMimeType($this->em->getRepository(Asset::class)->createQueryBuilder('a'), 'a.mimeType'),
            url: $url,
            width: self::FILE_TYPE_WIDTH,
        );
    }

    public function getTargetChart(?string $url = null): HomeChart
    {
        /** @var list<array{itemId: string, itemCount: int|string, totalSize: int|string|null}> $rows */
        $rows = $this->em->getRepository(Commit::class)->createQueryBuilder('c')
            ->select('IDENTITY(c.target) AS itemId, COUNT(c.id) AS itemCount, COALESCE(SUM(c.totalSize), 0) AS totalSize')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('since', DailyCounter::getDays(self::DAYS)[0], Types::DATETIME_IMMUTABLE)
            ->groupBy('c.target')
            ->orderBy('itemCount', 'DESC')
            ->setMaxResults(self::TARGET_COUNT)
            ->getQuery()
            ->getArrayResult();

        $names = [];
        $ids = array_map(static fn (array $row): string => (string) $row['itemId'], $rows);
        if ([] !== $ids) {
            /** @var Target[] $targets */
            $targets = $this->em->getRepository(Target::class)->findBy(['id' => $ids]);
            foreach ($targets as $target) {
                $names[$target->getId()] = (string) $target->getName();
            }
        }

        return $this->chartFactory->createTopChart(
            self::TARGET_TITLE,
            self::TARGET_ICON,
            $rows,
            $names,
            'Commits',
            'Volume',
            $url,
            self::TARGET_WIDTH,
        );
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
}
