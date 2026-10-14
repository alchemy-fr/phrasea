<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use App\Api\Model\Output\IntegrationDataOutput;
use App\Entity\Integration\IntegrationData;
use App\Integration\IntegrationDataTransformer;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: IntegrationDataOutput::class)]
readonly class IntegrationDataOutputMapper implements OutputMapperInterface
{
    public function __construct(private IntegrationDataTransformer $dataTransformer)
    {
    }

    public function supports(object $data): bool
    {
        return $data instanceof IntegrationData;
    }

    /**
     * @param IntegrationData $data
     */
    public function map(object $data, array $context = []): object
    {
        $this->dataTransformer->process($data);

        $output = new IntegrationDataOutput();
        $output->setId($data->getId());
        $output->setName($data->getName());
        $output->setValue($data->getValue());
        $output->setKeyId($data->getKeyId());

        return $output;
    }
}
