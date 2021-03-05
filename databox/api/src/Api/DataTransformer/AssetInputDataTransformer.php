<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\AssetInput;
use App\Entity\Core\Asset;
use Doctrine\ORM\EntityManagerInterface;

class AssetInputDataTransformer extends AbstractSecurityDataTransformer
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param AssetInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $asset = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Asset();
        $asset->setTitle($data->title);
        if (null !== $data->privacy) {
            $asset->setPrivacy($data->privacy);
        }

        if (isset($data->tags)) {
            $asset->getTags()->clear();
            foreach ($data->tags as $tag) {
                $asset->addTag($tag);
            }
        }

        return $asset;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Asset) {
            return false;
        }

        return Asset::class === $to && AssetInput::class === ($context['input']['class'] ?? null);
    }
}
