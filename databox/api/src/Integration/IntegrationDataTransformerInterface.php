<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\IntegrationFileData;

interface IntegrationDataTransformerInterface
{
    public function transformData(IntegrationFileData $data): void;

    public function supportData(string $integrationName, string $dataKey): bool;
}
