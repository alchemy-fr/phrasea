<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\IntegrationData;

class IntegrationDataTransformer
{
    /**
     * @var IntegrationDataTransformerInterface[]
     */
    private iterable $transformers;

    public function __construct(iterable $transformers)
    {
        $this->transformers = $transformers;
    }

    public function transformData(IntegrationData $data): void
    {
        foreach ($this->transformers as $transformer) {
            if ($transformer->supportData($data->getIntegration()->getIntegration(), $data->getName())) {
                $transformer->transformData($data);
            }
        }
    }
}
