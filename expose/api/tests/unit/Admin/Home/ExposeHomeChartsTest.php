<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin\Home;

use Alchemy\AdminBundle\Home\DailyCounter;
use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeStat;
use App\Admin\Home\ExposeHomeCharts;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ExposeHomeChartsTest extends TestCase
{
    private ExposeHomeCharts $charts;

    protected function setUp(): void
    {
        $this->charts = new ExposeHomeCharts($this->createStub(EntityManagerInterface::class));
    }

    public function testActivityChartHasOneLinePerSeries(): void
    {
        $days = DailyCounter::getDays(3, new \DateTimeImmutable('2026-11-01 10:00:00'));

        $chart = $this->charts->createActivityChart($days, [
            'Publications' => ['2026-10-30' => 1, '2026-10-31' => 0, '2026-11-01' => 2],
            'Assets' => ['2026-10-31' => 5],
            'Download requests' => [],
        ], '/admin/publications');

        $this->assertSame(ExposeHomeCharts::ACTIVITY_TITLE, $chart->title);
        $this->assertSame(HomeChart::TYPE_LINE, $chart->type);
        $this->assertSame(HomeChart::WIDTH_FULL, $chart->width);
        $this->assertSame('/admin/publications', $chart->url);
        $this->assertSame(['30/10', '31/10', '01/11'], $chart->labels);
        $this->assertCount(3, $chart->datasets);
        $this->assertSame('Publications', $chart->datasets[0]->label);
        $this->assertSame([1, 0, 2], $chart->datasets[0]->data);
        $this->assertSame('Assets', $chart->datasets[1]->label);
        $this->assertSame([0, 5, 0], $chart->datasets[1]->data);
        $this->assertSame('Download requests', $chart->datasets[2]->label);
        $this->assertSame([0, 0, 0], $chart->datasets[2]->data);
        $this->assertFalse($chart->isEmpty());
    }

    public function testFileTypeChartGroupsTheMimeTypesByFamily(): void
    {
        $chart = $this->charts->createFileTypeChart([
            ['mimeType' => 'image/jpeg', 'assetCount' => 3],
            ['mimeType' => 'application/pdf', 'assetCount' => '4'],
            ['mimeType' => 'image/png', 'assetCount' => 2],
            ['mimeType' => 'application/zip', 'assetCount' => 1],
        ]);

        $this->assertSame(ExposeHomeCharts::FILE_TYPE_TITLE, $chart->title);
        $this->assertSame(HomeChart::TYPE_DOUGHNUT, $chart->type);
        $this->assertSame(HomeChart::WIDTH_SMALL, $chart->width);
        $this->assertSame(['Image', 'Document', 'Other'], $chart->labels);
        $this->assertSame([5, 4, 1], $chart->datasets[0]->data);
    }

    public function testFileTypeChartWithoutAsset(): void
    {
        $chart = $this->charts->createFileTypeChart([]);

        $this->assertSame([], $chart->labels);
        $this->assertTrue($chart->isEmpty());
    }

    public function testPublicationChartHasTheSizeOnASecondAxis(): void
    {
        $chart = $this->charts->createPublicationChart([
            ['publicationId' => 'pub-1', 'assetCount' => '7', 'totalSize' => '4096'],
            ['publicationId' => 'pub-unknown', 'assetCount' => 2, 'totalSize' => null],
        ], ['pub-1' => 'Summer'], '/admin/publications');

        $this->assertSame(ExposeHomeCharts::PUBLICATION_TITLE, $chart->title);
        $this->assertSame(HomeChart::TYPE_BAR, $chart->type);
        $this->assertSame(HomeChart::WIDTH_LARGE, $chart->width);
        $this->assertTrue($chart->horizontal);
        $this->assertSame('/admin/publications', $chart->url);
        $this->assertSame(['Summer', 'pub-unknown'], $chart->labels);

        [$counts, $sizes] = $chart->datasets;
        $this->assertSame([7, 2], $counts->data);
        $this->assertNull($counts->axis);
        $this->assertSame([4096, 0], $sizes->data);
        $this->assertSame(HomeStat::FORMAT_BYTES, $sizes->format);
        $this->assertSame('x1', $sizes->axis);
    }
}
