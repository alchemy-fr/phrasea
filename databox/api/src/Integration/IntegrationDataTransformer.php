<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\IntegrationData;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class IntegrationDataTransformer
{
    /**
     * @param IntegrationDataTransformerInterface[] $transformers
     */
    public function __construct(
        #[AutowireIterator(tag: IntegrationDataTransformerInterface::TAG)]
        private iterable $transformers,
        private IntegrationManager $integrationManager,
    ) {
    }

    public function process(IntegrationData $data): void
    {
        $config = $this->integrationManager->getIntegrationConfiguration($data->getIntegration());

        foreach ($this->transformers as $transformer) {
            if ($transformer->supportData($data->getIntegration()->getIntegration(), $data->getName(), $config)) {
                $transformer->transformData($data, $config);
            }
        }
    }
}
