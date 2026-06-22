<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Exporter;

use App\Api\Model\Input\ExportEntitiesInput;
use App\Entity\Core\EntityList;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface AttributeEntityExporterInterface
{
    final public const string TAG = 'app.entity_exporter';

    public static function getName(): string;

    public function export(EntityList $entityList, ExportEntitiesInput $options): callable;

    public function getMimeType(ExportEntitiesInput $options): string;

    public function getFilename(EntityList $entityList, ExportEntitiesInput $options): string;
}
