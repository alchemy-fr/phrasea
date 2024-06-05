<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\IntegrationData;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: self::TAG)]
interface IntegrationDataTransformerInterface
{
    final public const TAG = 'app.integration.data';

    public function transformData(IntegrationData $data, IntegrationConfig $config): void;

    public function supportData(string $integrationName, string $dataName, IntegrationConfig $config): bool;
}
