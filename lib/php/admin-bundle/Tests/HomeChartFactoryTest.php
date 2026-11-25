<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeChartFactory;
use Alchemy\AdminBundle\Home\HomeStat;
use PHPUnit\Framework\TestCase;

class HomeChartFactoryTest extends TestCase
{
    public function testFileTypeChartGroupsTheMimeTypesByFamily(): void
    {
        $chart = (new HomeChartFactory())->createFileTypeChart('File types', 'fa-solid fa-chart-pie', [
            'image/jpeg' => 3,
            'application/pdf' => 4,
            'image/png' => 2,
            'application/zip' => 1,
            '' => 1,
        ], url: '/admin/assets');

        $this->assertSame('File types', $chart->title);
        $this->assertSame(HomeChart::TYPE_DOUGHNUT, $chart->type);
        $this->assertSame(HomeChart::WIDTH_SMALL, $chart->width);
        $this->assertSame('/admin/assets', $chart->url);
        $this->assertSame(['Image', 'Document', 'Other'], $chart->labels);
        $this->assertSame('Assets', $chart->datasets[0]->label);
        $this->assertSame([5, 4, 2], $chart->datasets[0]->data);
    }

    public function testFileTypeChartWithoutRow(): void
    {
        $chart = (new HomeChartFactory())->createFileTypeChart('File types', 'fa-solid fa-chart-pie', []);

        $this->assertSame([], $chart->labels);
        $this->assertTrue($chart->isEmpty());
    }

    public function testTopChartHasTheSizeOnASecondAxis(): void
    {
        $chart = (new HomeChartFactory())->createTopChart('Top', 'fa-solid fa-bullseye', [
            ['itemId' => 'i-1', 'itemCount' => '7', 'totalSize' => '4096'],
            ['itemId' => 'i-unknown', 'itemCount' => 2, 'totalSize' => null],
            ['itemId' => 'i-untitled', 'itemCount' => 1, 'totalSize' => 10],
        ], ['i-1' => 'Summer', 'i-untitled' => ''], 'Commits', 'Volume', '/admin/targets');

        $this->assertSame('Top', $chart->title);
        $this->assertSame(HomeChart::TYPE_BAR, $chart->type);
        $this->assertSame(HomeChart::WIDTH_LARGE, $chart->width);
        $this->assertTrue($chart->horizontal);
        $this->assertSame('/admin/targets', $chart->url);
        $this->assertSame(['Summer', 'i-unknown', 'i-untitled'], $chart->labels);

        [$counts, $sizes] = $chart->datasets;
        $this->assertSame('Commits', $counts->label);
        $this->assertSame([7, 2, 1], $counts->data);
        $this->assertNull($counts->axis);
        $this->assertSame('Volume', $sizes->label);
        $this->assertSame([4096, 0, 10], $sizes->data);
        $this->assertSame(HomeStat::FORMAT_BYTES, $sizes->format);
        $this->assertSame('x1', $sizes->axis);
    }
}
