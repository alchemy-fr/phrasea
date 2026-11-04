<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Core\Share;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Hands the share over to the normalization of its assets: their renditions
 * follow the selection of the link (see ShareReadProvider::isRenditionShared()).
 * The context only carries its ID, as the normalizers serialize the context
 * into their cache key.
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => 1042])]
final class ShareNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    final public const string CONTEXT_SHARE_ID = 'share_id';

    /**
     * @param Share $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::CONTEXT_SHARE_ID] = $data->getId();

        return $this->normalizer->normalize($data, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Share && ($context[self::CONTEXT_SHARE_ID] ?? null) !== $data->getId();
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Share::class => false,
        ];
    }
}
