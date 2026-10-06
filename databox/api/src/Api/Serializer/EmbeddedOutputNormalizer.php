<?php

declare(strict_types=1);

namespace App\Api\Serializer;

use App\Api\Mapper\Output\OutputMapperRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Exposes the resources embedded in a response (relations of an output DTO, items
 * of a non mapped collection...) through their output DTO, as the operation output
 * is only applied to its root object.
 *
 * Runs right before the API Platform item normalizers (-890 for JSON-LD).
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => -889])]
final class EmbeddedOutputNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function __construct(
        private readonly OutputMapperRegistry $outputMapperRegistry,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $outputClass = $this->getOutputClass($data, $context);
        $output = $this->outputMapperRegistry->map($data, $outputClass, $context);
        $context['output']['class'] = $outputClass;

        return $this->normalizer->normalize($output, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!\is_object($data) || $data instanceof \Traversable) {
            return false;
        }

        $outputClass = $this->getOutputClass($data, $context);

        return null !== $outputClass && $this->outputMapperRegistry->supports($data, $outputClass);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }

    private function getOutputClass(object $data, array $context): ?string
    {
        return $context['output']['class'] ?? $this->outputMapperRegistry->getOutputClass($data);
    }
}
