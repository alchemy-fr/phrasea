<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\IntegrationDataOutput;
use App\Entity\Integration\IntegrationData;

class IntegrationDataOutputDataTransformer extends AbstractSecurityDataTransformer
{
    /**
     * @param IntegrationData $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new IntegrationDataOutput();
        $output->setId($object->getId());
        $output->setName($object->getName());
        $output->setValue($object->getValue());

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return IntegrationDataOutput::class === $to && $data instanceof IntegrationData;
    }
}
