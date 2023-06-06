<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\IntegrationDataOutput;
use App\Entity\Integration\IntegrationData;
use App\Integration\IntegrationDataTransformer;

class IntegrationDataOutputDataTransformer extends AbstractSecurityDataTransformer
{
    public function __construct(private readonly IntegrationDataTransformer $transformer)
    {
    }

    /**
     * @param IntegrationData $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $this->transformer->transformData($object);

        $output = new IntegrationDataOutput();
        $output->setId($object->getId());
        $output->setName($object->getName());
        $output->setValue($object->getValue());
        $output->setKeyId($object->getKeyId());

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return IntegrationDataOutput::class === $to && $data instanceof IntegrationData;
    }
}
