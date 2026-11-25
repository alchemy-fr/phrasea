<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Home;

/**
 * A series of values of a HomeChart, one per label.
 */
final readonly class HomeChartDataset
{
    /**
     * @param list<int|float> $data
     * @param string          $format HomeStat::FORMAT_NUMBER or HomeStat::FORMAT_BYTES
     * @param ?string         $axis   the id of a secondary value axis (e.g. "x1", "y1") on which the
     *                                values are drawn, null for the main value axis
     * @param ?string         $type   HomeChart::TYPE_BAR or HomeChart::TYPE_LINE to draw this series
     *                                differently from the chart type (mixed chart), null for the chart type
     */
    public function __construct(
        public string $label,
        public array $data,
        public string $format = HomeStat::FORMAT_NUMBER,
        public ?string $axis = null,
        public ?string $type = null,
    ) {
    }
}
