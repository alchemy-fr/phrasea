<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\FileOutput;
use App\Entity\Core\File;
use App\Storage\UrlSigner;

class FileOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private UrlSigner $urlSigner;

    public function __construct(UrlSigner $urlSigner)
    {
        $this->urlSigner = $urlSigner;
    }

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
        $output->setUrl($this->urlSigner->getSignedUrl($object->getPath()));

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return FileOutput::class === $to && $data instanceof File;
    }
}
