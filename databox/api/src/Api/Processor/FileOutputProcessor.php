<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\AlternateUrlOutput;
use App\Api\Model\Output\FileOutput;
use App\Asset\FileUrlResolver;
use App\Entity\Core\AlternateUrl;
use App\Entity\Core\File;
use Doctrine\ORM\EntityManagerInterface;

class FileOutputProcessor extends AbstractSecurityProcessor
{
    private array $cache = [];

    public function __construct(private readonly FileUrlResolver $fileUrlResolver, private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param File $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $output = new FileOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->setType($data->getType());
        $output->setSize($data->getSize());

        if ($data->isPathPublic()) {
            $output->setUrl($this->fileUrlResolver->resolveUrl($data));
        }

        $urls = [];
        if (null !== $data->getAlternateUrls()) {
            foreach ($data->getAlternateUrls() as $type => $url) {
                $urls[] = new AlternateUrlOutput($type, $url, $this->resolveAlternateUrlLabel(
                    $data->getWorkspaceId(),
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
