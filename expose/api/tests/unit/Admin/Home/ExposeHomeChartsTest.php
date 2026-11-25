<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin\Home;

use Alchemy\AdminBundle\Home\DailyCounter;
use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeChartFactory;
use App\Admin\Home\ExposeHomeCharts;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ExposeHomeChartsTest extends TestCase
{
    private ExposeHomeCharts $charts;

    protected function setUp(): void
    {
        $this->charts = new ExposeHomeCharts($this->createStub(EntityManagerInterface::class), new HomeChartFactory());
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
}
