<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use App\Api\Model\Output\AlternateUrlOutput;
use App\Api\Model\Output\FileOutput;
use App\Asset\FileUrlResolver;
use App\Entity\Core\AlternateUrl;
use App\Entity\Core\File;
use App\Util\SecurityAwareTrait;
use Doctrine\ORM\EntityManagerInterface;

class FileOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;

    private array $cache = [];

    public function __construct(private readonly FileUrlResolver $fileUrlResolver, private readonly EntityManagerInterface $em)
    {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return FileOutput::class === $outputClass && $data instanceof File;
    }

    /**
     * @param File $data
     */
    public function transform(object $data, string $outputClass, array $context = []): object
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
}
