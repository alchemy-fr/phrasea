<?php

namespace App\Serializer\Normalizer;

use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Api\OutputTransformer\UserOutputTransformerTrait;
use App\Entity\Core\AssetExport;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[AutoconfigureTag('serializer.normalizer', ['priority' => 1042])]
#[Autoconfigure(public: true)]
class AssetExportNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use UserOutputTransformerTrait;

    private const string ALREADY_CALLED = self::class.'_AC';

    public function __construct(
        private readonly UrlSigner $urlSigner,
    ) {
    }

    /**
     * @param AssetExport $data
     */
    public function normalize(mixed $data, $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        $data->owner = $this->transformUser($data->getOwnerId());
        if (null !== $data->getPath()) {
            $data->downloadUrl = $this->urlSigner->getSignedUrl($data->getPath());
        }

        return $this->normalizer->normalize($data, $format, $context);
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof AssetExport;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            AssetExport::class => false,
        ];
    }
}
