<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Importer;

use App\Entity\Core\EntityList;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface AttributeEntityImporterInterface
{
    final public const string TAG = 'app.entity_importer';

    /**
     * Checks the data before the import is queued, so that its syntax errors are answered at once.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     */
    public function validate(EntityList $entityList, string $data, ?string $defaultLocale = null): void;

    /**
     * @param string|null                                  $defaultLocale locale of the labels given without one, for the formats supporting it
     * @param (\Closure(int $done, int $total): void)|null $onProgress    told of the progress, by the formats reporting it
     */
    public function import(EntityList $entityList, string $data, ?string $defaultLocale = null, ?\Closure $onProgress = null): void;
}
