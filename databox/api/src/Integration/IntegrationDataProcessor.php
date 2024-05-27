<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\IntegrationFileData;

readonly class IntegrationDataProcessor
{
    /**
     * @param IntegrationDataTransformerInterface[] $transformers
     */
    public function __construct(private iterable $transformers)
    {
    }

    public function process(IntegrationFileData $data): void
    {
        foreach ($this->transformers as $transformer) {
            if ($transformer->supportData($data->getIntegration()->getIntegration(), $data->getName())) {
                $transformer->transformData($data);
            }
        }
    }
}
