<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Exporter;

use App\Api\Model\Input\ExportEntitiesInput;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;

final readonly class JsonAttributeEntityExporter extends AbstractAttributeEntityExporter
{
    public static function getName(): string
    {
        return 'json';
    }

    public function export(EntityList $entityList, ExportEntitiesInput $options): callable
    {
        return function () use ($entityList, $options) {
            $data = [];

            $this->handle(function (AttributeEntity $result) use (&$data, $options): void {
                $d = [
                    'id' => $result->getId(),
                    'value' => $options->locale ? ($result->getTranslations()[$options->locale] ?? $result->getValue()) : $result->getValue(),
                    'emoji' => $result->getEmoji(),
                    'color' => $result->getColor(),
                    'status' => $result->getStatus(),
                    'external_id' => $result->getExternalId(),
                ];
                if (!$options->locale) {
                    $d['translations'] = $result->getTranslations();
                }

                $data[] = $d;
            }, $entityList->getId());
            echo json_encode($data);
        };
    }

    public function getMimeType(ExportEntitiesInput $options): string
    {
        return 'application/json';
    }

    public function getFilename(EntityList $entityList, ExportEntitiesInput $options): string
    {
        return $entityList->getName().($options->locale ? '-'.$options->locale : '').'.json';
    }
}
