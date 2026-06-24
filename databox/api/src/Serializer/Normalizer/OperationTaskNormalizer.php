<?php

namespace App\Serializer\Normalizer;

use App\Api\OutputTransformer\UserOutputTransformerTrait;
use App\Entity\Admin\OperationTask;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[AutoconfigureTag('serializer.normalizer', ['priority' => 1042])]
#[Autoconfigure(public: true)]
class OperationTaskNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use UserOutputTransformerTrait;

    private const string ALREADY_CALLED = self::class.'_AC';

    /**
     * @param OperationTask $object
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;

        $object->owner = $this->transformUser($object->getOwnerId());

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof OperationTask;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            OperationTask::class => false,
        ];
    }
}
