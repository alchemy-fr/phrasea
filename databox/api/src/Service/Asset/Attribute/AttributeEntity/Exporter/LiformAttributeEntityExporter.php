<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Exporter;

use App\Api\Model\Input\ExportEntitiesInput;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;

final readonly class LiformAttributeEntityExporter extends AbstractAttributeEntityExporter
{
    public static function getName(): string
    {
        return 'liform';
    }

    public function export(EntityList $entityList, ExportEntitiesInput $options): callable
    {
        return function () use ($entityList, $options) {
            $enum = [];
            $enumTitles = [];

            $this->handle(function (AttributeEntity $result) use (&$enum, &$enumTitles, $options): void {
                $enum[] = $result->getId();
                $enumTitles[] = $options->locale ? ($result->getTranslations()[$options->locale] ?? $result->getValue()) : $result->getValue();
            }, $entityList->getId());
            echo json_encode([
                'enum' => $enum,
                'enum_titles' => $enumTitles,
            ]);
        };
    }

    public function getMimeType(ExportEntitiesInput $options): string
    {
        return 'application/json';
    }

    public function getFilename(EntityList $entityList, ExportEntitiesInput $options): string
    {
        return $entityList->getName().($options->locale ? '-'.$options->locale : '').'.liform.json';
    }
}
