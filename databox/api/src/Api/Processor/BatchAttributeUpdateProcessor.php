<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\Attribute\AttributeBatchUpdateInput;
use App\Entity\Core\Attribute;

class BatchAttributeUpdateProcessor implements ProcessorInterface
{
    /**
     * @param AttributeBatchUpdateInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $object = new Attribute();
        $object->batchUpdate = $data;

        return $object;
    }
}
