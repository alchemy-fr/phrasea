<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Home;

/**
 * A chart of the admin home page, drawn by Chart.js.
 */
final readonly class HomeChart
{
    public const string TYPE_BAR = 'bar';
    public const string TYPE_LINE = 'line';
    public const string TYPE_DOUGHNUT = 'doughnut';

    /**
     * Widths of the chart card, in columns of the 12-column grid of the page (from the xl breakpoint).
     */
    public const int WIDTH_SMALL = 4;
    public const int WIDTH_MEDIUM = 6;
    public const int WIDTH_LARGE = 8;
    public const int WIDTH_FULL = 12;

    private const array WIDTHS = [self::WIDTH_SMALL, self::WIDTH_MEDIUM, self::WIDTH_LARGE, self::WIDTH_FULL];

    /**
     * @param list<string>           $labels
     * @param list<HomeChartDataset> $datasets
     * @param bool                   $horizontal for bars: the labels on the vertical axis
     * @param int                    $width      one of the WIDTH_* constants
     */
    public function __construct(
        public string $title,
        public string $icon,
        public string $type,
        public array $labels = [],
        public array $datasets = [],
        public bool $horizontal = false,
        public ?string $url = null,
        public ?string $error = null,
        public int $width = self::WIDTH_SMALL,
    ) {
        if (!in_array($width, self::WIDTHS, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid chart width %d, expected one of %s', $width, implode(', ', self::WIDTHS)));
        }
    }

    public static function failed(string $title, string $icon, \Throwable $error, ?string $url = null, int $width = self::WIDTH_SMALL): self
    {
        return new self(
            title: $title,
            icon: $icon,
            type: self::TYPE_BAR,
            url: $url,
            error: $error->getMessage(),
            width: $width,
        );
    }

    /**
     * Whether there is nothing to draw: no label, or only zero values.
     */
    public function isEmpty(): bool
    {
        if ([] === $this->labels) {
            return true;
        }
        foreach ($this->datasets as $dataset) {
            foreach ($dataset->data as $value) {
                if (0.0 !== (float) $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The Chart.js configuration. Its "formats" key is not a Chart.js option: it gives the
     * format (HomeStat::FORMAT_*) of the values of each dataset and of each value axis, which
     * the page turns into tick and tooltip formatters before handing the rest to Chart.js.
     *
     * @return array{type: string, data: array{labels: list<string>, datasets: list<array<string, mixed>>}, options: array<string, mixed>, formats: array{datasets: list<string>, axes: array<string, string>}}
     */
    public function toConfig(): array
    {
        $hasAxes = self::TYPE_DOUGHNUT !== $this->type;
        $mainAxis = $this->horizontal ? 'x' : 'y';

        $datasets = [];
        $datasetFormats = [];
        $axisFormats = [];
        foreach ($this->datasets as $dataset) {
            $config = [
                'label' => $dataset->label,
                'data' => $dataset->data,
            ];
            if (null !== $dataset->type) {
                $config['type'] = $dataset->type;
            }
            if ($hasAxes) {
                $axis = $dataset->axis ?? $mainAxis;
                if (null !== $dataset->axis) {
                    $config[$this->horizontal ? 'xAxisID' : 'yAxisID'] = $axis;
                }
                $axisFormats[$axis] ??= $dataset->format;
            }
            $datasets[] = $config;
            $datasetFormats[] = $dataset->format;
        }

        $options = [
            'plugins' => [
                'legend' => ['display' => !$hasAxes || count($this->datasets) > 1],
            ],
        ];
        if ($this->horizontal) {
            $options['indexAxis'] = 'y';
        }
        if ($hasAxes) {
            $scales = [];
            foreach (array_keys($axisFormats) as $axis) {
                $scale = ['beginAtZero' => true];
                if ($axis !== $mainAxis) {
                    $scale['position'] = $this->horizontal ? 'top' : 'right';
                    $scale['grid'] = ['drawOnChartArea' => false];
                }
                $scales[$axis] = $scale;
            }
            if ([] !== $scales) {
                $options['scales'] = $scales;
            }
        }

        return [
            'type' => $this->type,
            'data' => [
                'labels' => $this->labels,
                'datasets' => $datasets,
            ],
            'options' => $options,
            'formats' => [
                'datasets' => $datasetFormats,
                'axes' => $axisFormats,
            ],
        ];
    }
}
