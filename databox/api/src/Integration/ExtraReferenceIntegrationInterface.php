<?php

declare(strict_types=1);

namespace App\Integration;

interface ExtraReferenceIntegrationInterface
{
    /**
     * Extra reference sections (e.g. sub-modules documentation) displayed along the integration configuration reference.
     *
     * @return array<array{name: string, description: string|null, reference: string}>
     */
    public function getExtraReferenceSections(): array;
}
