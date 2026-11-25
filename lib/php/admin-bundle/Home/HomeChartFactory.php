<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Home;

use Doctrine\ORM\QueryBuilder;

/**
 * The charts shared by the admin home pages of the applications.
 */
final readonly class HomeChartFactory
{
    /**
     * Counts, in a single query, the rows of $qb (the root alias being the counted entity) per MIME type.
     *
     * @param string $mimeTypeField e.g. "a.mimeType"
     *
     * @return array<string, int> indexed by MIME type, the rows without one under ""
     */
    public function countByMimeType(QueryBuilder $qb, string $mimeTypeField): array
    {
        /** @var list<array{mimeType: ?string, rowCount: int|string}> $rows */
        $rows = (clone $qb)
            ->resetDQLPart('orderBy')
            ->select(sprintf('%s AS mimeType, COUNT(%s) AS rowCount', $mimeTypeField, $qb->getRootAliases()[0]))
            ->groupBy($mimeTypeField)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $mimeType = (string) $row['mimeType'];
            $counts[$mimeType] = ($counts[$mimeType] ?? 0) + (int) $row['rowCount'];
        }

        return $counts;
    }

    /**
     * A doughnut of the counts per file family.
     *
     * @param array<string, int> $counts indexed by MIME type
     */
    public function createFileTypeChart(
        string $title,
        string $icon,
        array $counts,
        string $datasetLabel = 'Assets',
        ?string $url = null,
        int $width = HomeChart::WIDTH_SMALL,
    ): HomeChart {
        $families = MimeTypeFamily::aggregate($counts);

        return new HomeChart(
            title: $title,
            icon: $icon,
            type: HomeChart::TYPE_DOUGHNUT,
            labels: array_keys($families),
            datasets: [new HomeChartDataset($datasetLabel, array_values($families))],
            url: $url,
            width: $width,
        );
    }

    /**
     * Horizontal bars of the top items: their count on the main axis, their total size on a
     * secondary axis ("x1").
     *
     * @param list<array{itemId: string, itemCount: int|string, totalSize: int|string|null}> $rows   in display order
     * @param array<string, string>                                                          $labels indexed by item ID, the unknown
     *                                                                                               or unlabelled items being labelled with their ID
     */
    public function createTopChart(
        string $title,
        string $icon,
        array $rows,
        array $labels,
        string $countLabel,
        string $sizeLabel,
        ?string $url = null,
        int $width = HomeChart::WIDTH_LARGE,
    ): HomeChart {
        $chartLabels = [];
        $counts = [];
        $sizes = [];
        foreach ($rows as $row) {
            $id = (string) $row['itemId'];
            $label = $labels[$id] ?? '';
            $chartLabels[] = '' !== $label ? $label : $id;
            $counts[] = (int) $row['itemCount'];
            $sizes[] = (int) ($row['totalSize'] ?? 0);
        }

        return new HomeChart(
            title: $title,
            icon: $icon,
            type: HomeChart::TYPE_BAR,
            labels: $chartLabels,
            datasets: [
                new HomeChartDataset($countLabel, $counts),
                new HomeChartDataset($sizeLabel, $sizes, HomeStat::FORMAT_BYTES, 'x1'),
            ],
            horizontal: true,
            url: $url,
            width: $width,
        );
    }
}
