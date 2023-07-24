<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\MoveAssetInput;
use App\Entity\Core\Asset;

class MoveAssetProcessor implements ProcessorInterface
{
    /**
     * @param MoveAssetInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $object = new Asset();
        $object->moveAction = $data;

        return $object;
    }
}
