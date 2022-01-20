<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\FileOutput;
use App\Asset\FileUrlResolver;
use App\Entity\Core\File;
use App\Storage\UrlSigner;
use RuntimeException;

class FileOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private FileUrlResolver $fileUrlResolver;

    public function __construct(FileUrlResolver $fileUrlResolver)
    {
        $this->fileUrlResolver = $fileUrlResolver;
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

        if ($object->isPathPublic()) {
            $output->setUrl($this->fileUrlResolver->resolveUrl($object));
        }

        $urls = [];
        if (null !== $object->getAlternateUrls()) {
            foreach ($object->getAlternateUrls() as $type => $url) {
                $urls[] = [
                    'type' => $type,
                    'url' => $url,
                ];
            }
        }

        $output->setAlternateUrls($urls);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return FileOutput::class === $to && $data instanceof File;
    }
}
