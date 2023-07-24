<?php

declare(strict_types=1);

namespace App\Api\DtoTransformer;

use App\Api\Model\Output\TagOutput;
use App\Entity\Core\Tag;

class TagOutputProcessor implements OutputTransformerInterface
{
    public function supports(string $outputClass, object $data): bool
    {
        return TagOutput::class === $outputClass && $data instanceof Tag;
    }

    /**
     * @param Tag $data
     */
    public function transform(object $data, string $outputClass, array $context = []): object
    {
        $output = new TagOutput();
        $output->setId($data->getId());
        $output->setName($data->getName());
        $output->setColor($data->getColor());

        return $output;
    }
}
