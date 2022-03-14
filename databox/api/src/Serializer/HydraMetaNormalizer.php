<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Api\Model\Output\ApiMetaWrapperOutput;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class HydraMetaNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    private NormalizerInterface $normalizer;

    public function __construct(NormalizerInterface $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function setNormalizer(NormalizerInterface $normalizer)
    {
        if ($this->normalizer instanceof NormalizerAwareInterface) {
            $this->normalizer->setNormalizer($normalizer);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null): bool
    {
        if ($data instanceof ApiMetaWrapperOutput) {
            return true;
        }

        return $this->normalizer->supportsNormalization($data, $format);
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        if ($object instanceof ApiMetaWrapperOutput) {
            $normalized = $this->normalizer->normalize($object->getResult(), $format, $context);

            return array_merge($normalized, $object->getMeta());
        }

        return $this->normalizer->normalize($object, $format, $context);
    }
}
