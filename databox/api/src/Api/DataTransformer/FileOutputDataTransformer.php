<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\AlternateUrlOutput;
use App\Api\Model\Output\FileOutput;
use App\Asset\FileUrlResolver;
use App\Entity\Core\AlternateUrl;
use App\Entity\Core\File;
use Doctrine\ORM\EntityManagerInterface;

class FileOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private array $cache = [];

    public function __construct(private readonly FileUrlResolver $fileUrlResolver, private readonly EntityManagerInterface $em)
    {
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
                $urls[] = new AlternateUrlOutput($type, $url, $this->resolveAlternateUrlLabel(
                    $object->getWorkspaceId(),
                    $type
                ));
            }
        }

        $output->setAlternateUrls($urls);

        return $output;
    }

    private function resolveAlternateUrlLabel(string $workspaceId, string $type): ?string
    {
        $key = sprintf('%s:%s', $workspaceId, $type);
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $label = $this->em->getRepository(AlternateUrl::class)
            ->findOneBy([
                'workspace' => $workspaceId,
                'type' => $type,
            ]);

        return $this->cache[$key] = $label instanceof AlternateUrl ? $label->getLabel() : null;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return FileOutput::class === $to && $data instanceof File;
    }
}
