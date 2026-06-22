<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Exporter;

use App\Api\Model\Input\ExportEntitiesInput;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Service\Asset\Attribute\AttributeEntity\Importer\CsvAttributeEntityImporter;

final readonly class CsvAttributeEntityExporter extends AbstractAttributeEntityExporter
{
    public static function getName(): string
    {
        return 'csv';
    }

    public function export(EntityList $entityList, ExportEntitiesInput $options): callable
    {
        $headers = [
            'id',
            'value',
            'emoji',
            'color',
            'status',
            'external_id',
        ];

        $allLocales = null;
        $locale = $options->locale;
        if (!$locale) {
            $allLocales = $entityList->getWorkspace()->getEnabledLocales();
            foreach ($allLocales as $l) {
                $headers[] = CsvAttributeEntityImporter::TRANSLATION_PREFIX.$l;
            }
        }

        return function () use ($entityList, $headers, $locale, $allLocales) {
            $stdout = fopen('php://output', 'w');
            $listId = $entityList->getId();

            fputcsv($stdout, $headers, escape: '\\');

            $this->handle(function (AttributeEntity $result) use ($stdout, $locale, $allLocales): void {
                fputcsv($stdout, [
                    $result->getId(),
                    $locale ? $result->getTranslations()[$locale] ?? '' : $result->getValue(),
                    $result->getEmoji(),
                    $result->getColor(),
                    $result->getStatus(),
                    $result->getExternalId(),
                    ...($allLocales ? array_map(fn (string $l): string => $result->getTranslations()[$l] ?? '', $allLocales) : []),
                ],
                    escape: '\\');
            }, $listId);
        };
    }

    public function getMimeType(ExportEntitiesInput $options): string
    {
        return 'text/csv';
    }

    public function getFilename(EntityList $entityList, ExportEntitiesInput $options): string
    {
        return $entityList->getName().($options->locale ? '-'.$options->locale : '').'.csv';
    }
}
