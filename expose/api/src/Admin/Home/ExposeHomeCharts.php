<?php

declare(strict_types=1);

namespace App\Admin\Home;

use Alchemy\AdminBundle\Home\DailyCounter;
use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeChartDataset;
use Alchemy\AdminBundle\Home\HomeChartFactory;
use App\Entity\Asset;
use App\Entity\DownloadRequest;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The charts of the admin home page.
 */
final readonly class ExposeHomeCharts
{
    public const string ACTIVITY_TITLE = 'Activity (30 days)';
    public const string ACTIVITY_ICON = 'fa-solid fa-chart-line';
    public const int ACTIVITY_WIDTH = HomeChart::WIDTH_FULL;
    public const string FILE_TYPE_TITLE = 'Assets by file type';
    public const string FILE_TYPE_ICON = 'fa-solid fa-chart-pie';
    public const int FILE_TYPE_WIDTH = HomeChart::WIDTH_SMALL;
    public const string PUBLICATION_TITLE = 'Top publications';
    public const string PUBLICATION_ICON = 'fa-solid fa-newspaper';
    public const int PUBLICATION_WIDTH = HomeChart::WIDTH_LARGE;

    private const int ACTIVITY_DAYS = 30;
    private const int PUBLICATION_COUNT = 10;

    public function __construct(
        private EntityManagerInterface $em,
        private HomeChartFactory $chartFactory,
    ) {
    }

    /**
     * One query per series.
     */
    public function getActivityChart(?string $url = null): HomeChart
    {
        $today = new \DateTimeImmutable();

        return $this->createActivityChart(DailyCounter::getDays(self::ACTIVITY_DAYS, $today), [
            'Publications' => $this->countPerDay(Publication::class, $today),
            'Assets' => $this->countPerDay(Asset::class, $today),
            'Download requests' => $this->countPerDay(DownloadRequest::class, $today),
        ], $url);
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

    public function getPublicationChart(?string $url = null): HomeChart
    {
        /** @var list<array{itemId: string, itemCount: int|string, totalSize: int|string|null}> $rows */
        $rows = $this->em->getRepository(Asset::class)->createQueryBuilder('a')
            ->select('IDENTITY(a.publication) AS itemId, COUNT(a.id) AS itemCount, COALESCE(SUM(a.size), 0) AS totalSize')
            ->groupBy('a.publication')
            ->orderBy('itemCount', 'DESC')
            ->setMaxResults(self::PUBLICATION_COUNT)
            ->getQuery()
            ->getArrayResult();

        $titles = [];
        $ids = array_map(static fn (array $row): string => (string) $row['itemId'], $rows);
        if ([] !== $ids) {
            /** @var Publication[] $publications */
            $publications = $this->em->getRepository(Publication::class)->findBy(['id' => $ids]);
            foreach ($publications as $publication) {
                $titles[$publication->getId()] = (string) $publication->getTitle();
            }
        }

        return $this->chartFactory->createTopChart(
            self::PUBLICATION_TITLE,
            self::PUBLICATION_ICON,
            $rows,
            $titles,
            'Assets',
            'Total size',
            $url,
            self::PUBLICATION_WIDTH,
        );
    }

    /**
     * @param list<\DateTimeImmutable>          $days
     * @param array<string, array<string, int>> $series counts indexed by day ("Y-m-d"), indexed by series label
     */
    public function createActivityChart(array $days, array $series, ?string $url = null): HomeChart
    {
        $datasets = [];
        foreach ($series as $label => $counts) {
            $datasets[] = new HomeChartDataset(
                (string) $label,
                array_map(static fn (\DateTimeImmutable $day): int => $counts[$day->format('Y-m-d')] ?? 0, $days),
            );
        }

        return new HomeChart(
            title: self::ACTIVITY_TITLE,
            icon: self::ACTIVITY_ICON,
            type: HomeChart::TYPE_LINE,
            labels: array_map(DailyCounter::formatLabel(...), $days),
            datasets: $datasets,
            url: $url,
            width: self::ACTIVITY_WIDTH,
        );
    }

    /**
     * @param class-string $entity an entity with a "createdAt" field
     *
     * @return array<string, int>
     */
    private function countPerDay(string $entity, \DateTimeImmutable $today): array
    {
        return DailyCounter::fromQueryBuilder(
            $this->em->getRepository($entity)->createQueryBuilder('e'),
            'e.createdAt',
            self::ACTIVITY_DAYS,
            today: $today,
        );
    }
}
