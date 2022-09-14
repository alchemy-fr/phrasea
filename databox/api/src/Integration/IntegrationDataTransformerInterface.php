<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\IntegrationData;

interface IntegrationDataTransformerInterface
{
    public function transformData(IntegrationData $data): void;

    public function supportData(string $integrationName, string $dataKey): bool;
}
