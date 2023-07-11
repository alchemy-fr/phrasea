<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\IntegrationDataOutput;
use App\Entity\Integration\IntegrationData;
use App\Integration\IntegrationDataProcessor;

class IntegrationDataOutputProcessor extends AbstractSecurityProcessor
{
    public function __construct(private readonly IntegrationDataProcessor $dataProcessor)
    {
    }

    /**
     * @param IntegrationData $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->dataProcessor->process($data);

        $output = new IntegrationDataOutput();
        $output->setId($data->getId());
        $output->setName($data->getName());
        $output->setValue($data->getValue());
        $output->setKeyId($data->getKeyId());

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return IntegrationDataOutput::class === $to && $data instanceof IntegrationData;
    }
}
