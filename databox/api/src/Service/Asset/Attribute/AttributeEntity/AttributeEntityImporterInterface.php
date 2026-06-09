<?php

namespace App\Service\Asset\Attribute\AttributeEntity;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface AttributeEntityImporterInterface
{
    final public const string TAG = 'app.entity_importer';

    public static function getName(): string;

    /**
     * @return AttributeEntity[]
     */
    public function import(EntityList $entityList, string $data): void;
}
