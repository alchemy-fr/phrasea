<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK;

interface ReportClientInterface
{
    public function pushLog(
        string $action,
        ?string $userId = null,
        ?string $itemId = null,
        array $payload = [],
    ): void;
}
