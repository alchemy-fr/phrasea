<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin\Home;

use Alchemy\AdminBundle\Home\DailyCounter;
use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeChartFactory;
use Alchemy\AdminBundle\Home\HomeStat;
use App\Admin\Home\UploaderHomeCharts;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class UploaderHomeChartsTest extends TestCase
{
    private UploaderHomeCharts $charts;

    protected function setUp(): void
    {
        $this->charts = new UploaderHomeCharts($this->createStub(EntityManagerInterface::class), new HomeChartFactory());
    }

    public function testUploadsChartDrawsTheVolumeAsALineOnASecondAxis(): void
    {
        $days = DailyCounter::getDays(3, new \DateTimeImmutable('2026-11-01 10:00:00'));

        $chart = $this->charts->createUploadsChart(
            $days,
            ['2026-10-30' => 2, '2026-11-01' => 1],
            ['2026-10-30' => 4096, '2026-11-01' => 1024],
            '/admin/commits',
        );

        $this->assertSame(UploaderHomeCharts::UPLOADS_TITLE, $chart->title);
        $this->assertSame(HomeChart::TYPE_BAR, $chart->type);
        $this->assertSame(HomeChart::WIDTH_FULL, $chart->width);
        $this->assertSame('/admin/commits', $chart->url);
        $this->assertSame(['30/10', '31/10', '01/11'], $chart->labels);

        [$commits, $volume] = $chart->datasets;
        $this->assertSame('Commits', $commits->label);
        $this->assertSame([2, 0, 1], $commits->data);
        $this->assertNull($commits->type);
        $this->assertNull($commits->axis);
        $this->assertSame('Volume', $volume->label);
        $this->assertSame([4096, 0, 1024], $volume->data);
        $this->assertSame(HomeChart::TYPE_LINE, $volume->type);
        $this->assertSame(HomeStat::FORMAT_BYTES, $volume->format);
        $this->assertSame('y1', $volume->axis);
    }
}
