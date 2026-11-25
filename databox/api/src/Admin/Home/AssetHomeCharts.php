<?php

declare(strict_types=1);

namespace App\Admin\Home;

use Alchemy\AdminBundle\Home\DailyCounter;
use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeChartDataset;
use Alchemy\AdminBundle\Home\HomeStat;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation\AbstractAggregation;
use Elastica\Aggregation\DateHistogram;
use Elastica\Aggregation\Sum;
use Elastica\Aggregation\Terms;
use Elastica\Index;
use Elastica\Query;

/**
 * The charts of the admin home page about assets, computed by Elasticsearch aggregations
 * on the non-deleted assets: assets are never queried in SQL.
 */
final readonly class AssetHomeCharts
{
    public const string TIMELINE_TITLE = 'Assets created (30 days)';
    public const string TIMELINE_ICON = 'fa-solid fa-chart-column';
    public const int TIMELINE_WIDTH = HomeChart::WIDTH_FULL;
    public const string FAMILY_TITLE = 'Assets by file family';
    public const string FAMILY_ICON = 'fa-solid fa-chart-pie';
    public const int FAMILY_WIDTH = HomeChart::WIDTH_SMALL;
    public const string WORKSPACE_TITLE = 'Assets by workspace';
    public const string WORKSPACE_ICON = 'fa-solid fa-briefcase';
    public const int WORKSPACE_WIDTH = HomeChart::WIDTH_LARGE;

    private const int TIMELINE_DAYS = 30;
    private const int WORKSPACE_COUNT = 10;
    private const string NO_FAMILY_KEY = '__none__';
    private const string AGGREGATION = 'chart';
    private const string SIZE_AGGREGATION = 'size';

    public function __construct(
        private Index $assetIndex,
        private EntityManagerInterface $em,
    ) {
    }

    public function getTimelineChart(?string $url = null): HomeChart
    {
        $days = DailyCounter::getDays(self::TIMELINE_DAYS);
        $since = $days[0];
        $until = $days[count($days) - 1];

        $aggregation = new DateHistogram(self::AGGREGATION, 'createdAt', 'day');
        $aggregation->setTimezone(date_default_timezone_get());
        $aggregation->setFormat('yyyy-MM-dd');
        $aggregation->setMinimumDocumentCount(0);
        $aggregation->setExtendedBounds($since->format('Y-m-d'), $until->format('Y-m-d'));

        return $this->createTimelineChart($this->aggregate($aggregation, $since), $since, $until, $url);
    }

    public function getFamilyChart(?string $url = null): HomeChart
    {
        $aggregation = new Terms(self::AGGREGATION);
        $aggregation->setField('fileFamily');
        $aggregation->setMissing(self::NO_FAMILY_KEY);
        $aggregation->setSize(10);

        return $this->createFamilyChart($this->aggregate($aggregation), $url);
    }

    public function getWorkspaceChart(?string $url = null): HomeChart
    {
        $aggregation = new Terms(self::AGGREGATION);
        $aggregation->setField('workspaceId');
        $aggregation->setSize(self::WORKSPACE_COUNT);
        $aggregation->setOrder('_count', 'desc');
        $aggregation->addAggregation((new Sum(self::SIZE_AGGREGATION))->setField('fileSize'));

        $buckets = $this->aggregate($aggregation);

        $workspaceNames = [];
        $ids = array_map(static fn (array $bucket): string => (string) $bucket['key'], $buckets);
        if ([] !== $ids) {
            /** @var Workspace[] $workspaces */
            $workspaces = $this->em->getRepository(Workspace::class)->findBy(['id' => $ids]);
            foreach ($workspaces as $workspace) {
                $workspaceNames[$workspace->getId()] = $workspace->getName();
            }
        }

        return $this->createWorkspaceChart($buckets, $workspaceNames, $url);
    }

    /**
     * One bar per day from $since to $until (included), the days without bucket counting zero.
     *
     * @param list<array{key: int|string, key_as_string?: string, doc_count: int}> $buckets
     */
    public function createTimelineChart(array $buckets, \DateTimeImmutable $since, \DateTimeImmutable $until, ?string $url = null): HomeChart
    {
        $counts = [];
        foreach ($buckets as $bucket) {
            $day = $bucket['key_as_string']
                ?? new \DateTimeImmutable('@'.intdiv((int) $bucket['key'], 1000))->setTimezone($since->getTimezone())->format('Y-m-d');
            $counts[$day] = ($counts[$day] ?? 0) + (int) $bucket['doc_count'];
        }

        $labels = [];
        $data = [];
        $until = $until->setTime(0, 0);
        for ($day = $since->setTime(0, 0); $day <= $until; $day = $day->modify('+1 day')) {
            $labels[] = DailyCounter::formatLabel($day);
            $data[] = $counts[$day->format('Y-m-d')] ?? 0;
        }

        return new HomeChart(
            title: self::TIMELINE_TITLE,
            icon: self::TIMELINE_ICON,
            type: HomeChart::TYPE_BAR,
            labels: $labels,
            datasets: [new HomeChartDataset('Assets', $data)],
            url: $url,
            width: self::TIMELINE_WIDTH,
        );
    }

    /**
     * @param list<array{key: string, doc_count: int}> $buckets
     */
    public function createFamilyChart(array $buckets, ?string $url = null): HomeChart
    {
        $labels = [];
        $data = [];
        foreach ($buckets as $bucket) {
            $key = (string) $bucket['key'];
            $labels[] = self::NO_FAMILY_KEY === $key ? 'No file' : ucfirst($key);
            $data[] = (int) $bucket['doc_count'];
        }

        return new HomeChart(
            title: self::FAMILY_TITLE,
            icon: self::FAMILY_ICON,
            type: HomeChart::TYPE_DOUGHNUT,
            labels: $labels,
            datasets: [new HomeChartDataset('Assets', $data)],
            url: $url,
            width: self::FAMILY_WIDTH,
        );
    }

    /**
     * @param list<array{key: string, doc_count: int, size?: array{value: int|float|null}}> $buckets
     * @param array<string, string>                                                         $workspaceNames indexed by workspace ID, the
     *                                                                                                      unknown ones being labelled with their ID
     */
    public function createWorkspaceChart(array $buckets, array $workspaceNames, ?string $url = null): HomeChart
    {
        $labels = [];
        $counts = [];
        $sizes = [];
        foreach ($buckets as $bucket) {
            $id = (string) $bucket['key'];
            $labels[] = $workspaceNames[$id] ?? $id;
            $counts[] = (int) $bucket['doc_count'];
            $sizes[] = (int) ($bucket[self::SIZE_AGGREGATION]['value'] ?? 0);
        }

        return new HomeChart(
            title: self::WORKSPACE_TITLE,
            icon: self::WORKSPACE_ICON,
            type: HomeChart::TYPE_BAR,
            labels: $labels,
            datasets: [
                new HomeChartDataset('Assets', $counts),
                new HomeChartDataset('Total size', $sizes, HomeStat::FORMAT_BYTES, 'x1'),
            ],
            horizontal: true,
            url: $url,
            width: self::WORKSPACE_WIDTH,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aggregate(AbstractAggregation $aggregation, ?\DateTimeImmutable $since = null): array
    {
        $boolQuery = new Query\BoolQuery();
        $boolQuery->addFilter(new Query\Term(['deleted' => false]));
        if (null !== $since) {
            $boolQuery->addFilter(new Query\Range('createdAt', ['gte' => $since->format(\DateTimeInterface::ATOM)]));
        }

        $query = new Query($boolQuery);
        $query->setSize(0);
        $query->addAggregation($aggregation);

        return array_values($this->assetIndex->search($query)->getAggregation(self::AGGREGATION)['buckets'] ?? []);
    }
}
