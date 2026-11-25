<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeChartDataset;
use Alchemy\AdminBundle\Home\HomeStat;
use PHPUnit\Framework\TestCase;

class HomeChartTest extends TestCase
{
    public function testBarConfig(): void
    {
        $chart = new HomeChart('Created', 'fa-solid fa-chart-column', HomeChart::TYPE_BAR, ['01/11', '02/11'], [
            new HomeChartDataset('Assets', [3, 0]),
        ]);

        $this->assertSame([
            'type' => 'bar',
            'data' => [
                'labels' => ['01/11', '02/11'],
                'datasets' => [
                    ['label' => 'Assets', 'data' => [3, 0]],
                ],
            ],
            'options' => [
                'plugins' => ['legend' => ['display' => false]],
                'scales' => [
                    'y' => ['beginAtZero' => true],
                ],
            ],
            'formats' => [
                'datasets' => [HomeStat::FORMAT_NUMBER],
                'axes' => ['y' => HomeStat::FORMAT_NUMBER],
            ],
        ], $chart->toConfig());
    }

    public function testHorizontalConfigWithASecondAxis(): void
    {
        $chart = new HomeChart('By workspace', 'fa-solid fa-briefcase', HomeChart::TYPE_BAR, ['A', 'B'], [
            new HomeChartDataset('Assets', [10, 2]),
            new HomeChartDataset('Size', [2048, 1024], HomeStat::FORMAT_BYTES, 'x1'),
        ], horizontal: true);

        $this->assertSame([
            'type' => 'bar',
            'data' => [
                'labels' => ['A', 'B'],
                'datasets' => [
                    ['label' => 'Assets', 'data' => [10, 2]],
                    ['label' => 'Size', 'data' => [2048, 1024], 'xAxisID' => 'x1'],
                ],
            ],
            'options' => [
                'plugins' => ['legend' => ['display' => true]],
                'indexAxis' => 'y',
                'scales' => [
                    'x' => ['beginAtZero' => true],
                    'x1' => ['beginAtZero' => true, 'position' => 'top', 'grid' => ['drawOnChartArea' => false]],
                ],
            ],
            'formats' => [
                'datasets' => [HomeStat::FORMAT_NUMBER, HomeStat::FORMAT_BYTES],
                'axes' => ['x' => HomeStat::FORMAT_NUMBER, 'x1' => HomeStat::FORMAT_BYTES],
            ],
        ], $chart->toConfig());
    }

    public function testDoughnutConfig(): void
    {
        $chart = new HomeChart('By family', 'fa-solid fa-chart-pie', HomeChart::TYPE_DOUGHNUT, ['Image', 'No file'], [
            new HomeChartDataset('Assets', [5, 1]),
        ]);

        $this->assertSame([
            'type' => 'doughnut',
            'data' => [
                'labels' => ['Image', 'No file'],
                'datasets' => [
                    ['label' => 'Assets', 'data' => [5, 1]],
                ],
            ],
            'options' => [
                'plugins' => ['legend' => ['display' => true]],
            ],
            'formats' => [
                'datasets' => [HomeStat::FORMAT_NUMBER],
                'axes' => [],
            ],
        ], $chart->toConfig());
    }

    public function testFailed(): void
    {
        $chart = HomeChart::failed('By family', 'fa-solid fa-chart-pie', new \RuntimeException('ES is down'), '/admin/assets');

        $this->assertSame('By family', $chart->title);
        $this->assertSame('fa-solid fa-chart-pie', $chart->icon);
        $this->assertSame('/admin/assets', $chart->url);
        $this->assertSame('ES is down', $chart->error);
        $this->assertSame([], $chart->labels);
        $this->assertSame([], $chart->datasets);
    }
}
