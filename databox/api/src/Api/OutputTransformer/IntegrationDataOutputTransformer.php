<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use App\Api\Model\Output\IntegrationDataOutput;
use App\Entity\Integration\IntegrationFileData;
use App\Integration\IntegrationDataProcessor;

readonly class IntegrationDataOutputTransformer implements OutputTransformerInterface
{
    public function __construct(private IntegrationDataProcessor $dataProcessor)
    {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return IntegrationDataOutput::class === $outputClass && $data instanceof IntegrationFileData;
    }

    /**
     * @param IntegrationFileData $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $this->dataProcessor->process($data);

        $output = new IntegrationDataOutput();
        $output->setId($data->getId());
        $output->setName($data->getName());
        $output->setValue($data->getValue());
        $output->setKeyId($data->getKeyId());

        return $output;
    }
}
