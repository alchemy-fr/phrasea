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

    public function testMixedConfigGivesTheTypeOfEachDataset(): void
    {
        $chart = new HomeChart('Uploads', 'fa-solid fa-chart-column', HomeChart::TYPE_BAR, ['01/11', '02/11'], [
            new HomeChartDataset('Commits', [1, 2]),
            new HomeChartDataset('Volume', [2048, 0], HomeStat::FORMAT_BYTES, 'y1', HomeChart::TYPE_LINE),
        ], width: HomeChart::WIDTH_FULL);

        $config = $chart->toConfig();
        $this->assertSame('bar', $config['type']);
        $this->assertSame([
            ['label' => 'Commits', 'data' => [1, 2]],
            ['label' => 'Volume', 'data' => [2048, 0], 'type' => 'line', 'yAxisID' => 'y1'],
        ], $config['data']['datasets']);
        $this->assertSame(['y' => HomeStat::FORMAT_NUMBER, 'y1' => HomeStat::FORMAT_BYTES], $config['formats']['axes']);
        $this->assertSame([
            'y' => ['beginAtZero' => true],
            'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]],
        ], $config['options']['scales']);
        $this->assertArrayNotHasKey('width', $config);
    }

    public function testWidthDefaultsToSmall(): void
    {
        $this->assertSame(HomeChart::WIDTH_SMALL, new HomeChart('Created', 'fa-solid fa-chart-column', HomeChart::TYPE_BAR)->width);
        $this->assertSame(HomeChart::WIDTH_LARGE, new HomeChart('Created', 'fa-solid fa-chart-column', HomeChart::TYPE_BAR, width: HomeChart::WIDTH_LARGE)->width);
    }

    public function testInvalidWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HomeChart('Created', 'fa-solid fa-chart-column', HomeChart::TYPE_BAR, width: 5);
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue(new HomeChart('Created', 'fa-solid fa-chart-column', HomeChart::TYPE_BAR)->isEmpty());
        $this->assertTrue(new HomeChart('Created', 'fa-solid fa-chart-column', HomeChart::TYPE_BAR, ['01/11', '02/11'], [
            new HomeChartDataset('Assets', [0, 0]),
            new HomeChartDataset('Size', [0, 0.0], HomeStat::FORMAT_BYTES, 'y1'),
        ])->isEmpty());
        $this->assertFalse(new HomeChart('Created', 'fa-solid fa-chart-column', HomeChart::TYPE_BAR, ['01/11', '02/11'], [
            new HomeChartDataset('Assets', [0, 0]),
            new HomeChartDataset('Size', [0, 12], HomeStat::FORMAT_BYTES, 'y1'),
        ])->isEmpty());
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
        $this->assertSame(HomeChart::WIDTH_SMALL, $chart->width);
    }

    public function testFailedKeepsTheWidth(): void
    {
        $chart = HomeChart::failed('Timeline', 'fa-solid fa-chart-column', new \RuntimeException('ES is down'), width: HomeChart::WIDTH_FULL);

        $this->assertSame(HomeChart::WIDTH_FULL, $chart->width);
    }
}
