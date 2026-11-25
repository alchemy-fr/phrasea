<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin\Home;

use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeStat;
use App\Admin\Home\AssetHomeCharts;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Index;
use PHPUnit\Framework\TestCase;

class AssetHomeChartsTest extends TestCase
{
    private AssetHomeCharts $charts;

    protected function setUp(): void
    {
        $this->charts = new AssetHomeCharts($this->createStub(Index::class), $this->createStub(EntityManagerInterface::class));
    }

    public function testTimelineFillsTheMissingDays(): void
    {
        $timezone = new \DateTimeZone('Europe/Paris');
        $since = new \DateTimeImmutable('2026-10-30', $timezone);
        $until = new \DateTimeImmutable('2026-11-02', $timezone);

        $chart = $this->charts->createTimelineChart([
            ['key' => 1793314800000, 'key_as_string' => '2026-10-30', 'doc_count' => 4],
            // Without key_as_string: 2026-11-01 00:00 in Paris
            ['key' => 1793487600000, 'doc_count' => 2],
        ], $since, $until, '/admin/assets');

        $this->assertSame(AssetHomeCharts::TIMELINE_TITLE, $chart->title);
        $this->assertSame(HomeChart::TYPE_BAR, $chart->type);
        $this->assertFalse($chart->horizontal);
        $this->assertSame('/admin/assets', $chart->url);
        $this->assertSame(['30/10', '31/10', '01/11', '02/11'], $chart->labels);
        $this->assertCount(1, $chart->datasets);
        $this->assertSame([4, 0, 2, 0], $chart->datasets[0]->data);
    }

    public function testFamilyChartLabelsTheAssetsWithoutFile(): void
    {
        $chart = $this->charts->createFamilyChart([
            ['key' => 'image', 'doc_count' => 12],
            ['key' => '__none__', 'doc_count' => 3],
            ['key' => 'video', 'doc_count' => 1],
        ]);

        $this->assertSame(AssetHomeCharts::FAMILY_TITLE, $chart->title);
        $this->assertSame(HomeChart::TYPE_DOUGHNUT, $chart->type);
        $this->assertSame(['Image', 'No file', 'Video'], $chart->labels);
        $this->assertSame([12, 3, 1], $chart->datasets[0]->data);
    }

    public function testWorkspaceChartHasTheSizeOnASecondAxis(): void
    {
        $chart = $this->charts->createWorkspaceChart([
            ['key' => 'ws-1', 'doc_count' => 10, 'size' => ['value' => 2048.0]],
            ['key' => 'ws-unknown', 'doc_count' => 2, 'size' => ['value' => 0.0]],
        ], ['ws-1' => 'Marketing']);

        $this->assertSame(AssetHomeCharts::WORKSPACE_TITLE, $chart->title);
        $this->assertTrue($chart->horizontal);
        $this->assertSame(['Marketing', 'ws-unknown'], $chart->labels);
        $this->assertCount(2, $chart->datasets);

        [$counts, $sizes] = $chart->datasets;
        $this->assertSame([10, 2], $counts->data);
        $this->assertSame(HomeStat::FORMAT_NUMBER, $counts->format);
        $this->assertNull($counts->axis);
        $this->assertSame([2048, 0], $sizes->data);
        $this->assertSame(HomeStat::FORMAT_BYTES, $sizes->format);
        $this->assertSame('x1', $sizes->axis);
    }
}
