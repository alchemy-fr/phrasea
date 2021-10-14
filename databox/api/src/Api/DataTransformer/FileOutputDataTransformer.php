<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\FileOutput;
use App\Entity\Core\File;

class FileOutputDataTransformer extends AbstractSecurityDataTransformer
{
    /**
     * @param File $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new FileOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->setType($object->getType());
        $output->setSize($object->getSize());
        $output->setUrl('https://www.publicdomainpictures.net/pictures/320000/velka/background-image.png'); // TODO

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return FileOutput::class === $to && $data instanceof File;
    }
}
