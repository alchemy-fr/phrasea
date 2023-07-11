<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Output\TagOutput;
use App\Entity\Core\Tag;

class TagOutputProcessor implements ProcessorInterface
{
    /**
     * @param Tag $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $output = new TagOutput();
        $output->setId($data->getId());
        $output->setName($data->getName());
        $output->setColor($data->getColor());

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return TagOutput::class === $to && $data instanceof Tag;
    }
}
