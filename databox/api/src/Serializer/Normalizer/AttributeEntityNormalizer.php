<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Core\AttributeEntity;
use App\Service\Image\ImageSetManager;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Adds the URLs of the value image ("image", "thumbnail") to the normalized value.
 * The image follows the value: whoever may read the value sees its image.
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => 1042])]
final class AttributeEntityNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string CONTEXT_KEY = 'attribute_entity_image';

    public function __construct(
        private readonly ImageSetManager $imageSetManager,
    ) {
    }

    /**
     * @param AttributeEntity $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::CONTEXT_KEY] = $data->getId();

        $normalized = $this->normalizer->normalize($data, $format, $context);
        if (!\is_array($normalized)) {
            return $normalized;
        }

        $imageSet = $data->getImage();
        $skipNull = $context[AbstractObjectNormalizer::SKIP_NULL_VALUES] ?? false;
        foreach ([
            'image' => $this->imageSetManager->resolveImageUrl($imageSet),
            'thumbnail' => $this->imageSetManager->resolveThumbnailUrl($imageSet),
        ] as $key => $url) {
            if (null !== $url || !$skipNull) {
                $normalized[$key] = $url;
            }
        }

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AttributeEntity && ($context[self::CONTEXT_KEY] ?? null) !== $data->getId();
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            AttributeEntity::class => false,
        ];
    }
}
