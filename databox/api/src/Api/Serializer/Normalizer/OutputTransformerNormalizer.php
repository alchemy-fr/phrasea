<?php

declare(strict_types=1);

namespace App\Api\Serializer\Normalizer;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Util\ClassInfoTrait;
use ApiPlatform\Serializer\InputOutputMetadataTrait;
use App\Api\OutputTransformer\OutputTransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsDecorator('api_platform.jsonld.normalizer.item')]
final class OutputTransformerNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    use InputOutputMetadataTrait;
    use ClassInfoTrait;

    /**
     * @var array<class-string, string|null>
     */
    private array $outputClassByClass = [];

    public function __construct(
        private readonly NormalizerInterface $decorated,
        /**
         * @var OutputTransformerInterface[]
         */
        #[TaggedIterator(OutputTransformerInterface::TAG)]
        private readonly iterable $transformers,
        ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null,
        private readonly ?IriConverterInterface $iriConverter = null,
    ) {
        $this->resourceMetadataCollectionFactory = $resourceMetadataCollectionFactory;
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): \ArrayObject|array|string|int|float|bool|null
    {
        if (is_object($data) && !is_iterable($data)) {
            if (null !== $outputClass = $this->getOutputClass($data)) {
                $context['output']['class'] = $outputClass;
                $context['real_resource_class'] = [
                    'class' => $this->getObjectClass($data),
                    'output' => $outputClass,
                ];

                $output = $this->transform($data, $outputClass, $context);

                return $this->decorated->normalize($output, $format, $context);
            }

            // Second pass on an output DTO (API Platform unsets "output" and normalizes the DTO
            // as an anonymous resource): expose the original entity IRI and type so that "@id"
            // does not fall back to a "/.well-known/genid/" URI and "@type" to the DTO class name.
            if (isset($context['api_platform_output_class'], $context['api_resource'])
                && $data::class === $context['api_platform_output_class']
                && !isset($context['output']['types'])
            ) {
                $resource = $context['api_resource'];
                $context['output']['types'] = [$this->resourceMetadataCollectionFactory->create($this->getObjectClass($resource))[0]->getShortName()];

                if (null !== $this->iriConverter) {
                    try {
                        $context['output']['iri'] = $this->iriConverter->getIriFromResource($resource);
                        $context['output']['gen_id'] = false;
                        // Prevent the JsonLd normalizer from resetting the IRI of collection members
                        unset($context['api_collection_sub_level']);
                    } catch (\Exception) {
                        // Keep the generated id when the entity has no IRI
                    }
                }
            }
        }

        return $this->decorated->normalize($data, $format, $context);
    }

    private function getOutputClass(object $object): ?string
    {
        $class = $this->getObjectClass($object);
        if (array_key_exists($class, $this->outputClassByClass)) {
            return $this->outputClassByClass[$class];
        }

        $outputClass = null;
        $metadata = $this->resourceMetadataCollectionFactory->create($class);
        foreach ($metadata as $m) {
            if (null !== $output = $m->getOutput()) {
                $outputClass = $output['class'];
                break;
            }
        }

        return $this->outputClassByClass[$class] = $outputClass;
    }

    private function transform(object $object, string $outputClass, array &$context): object
    {
        foreach ($this->transformers as $transformer) {
            if ($transformer->supports($outputClass, $object)) {
                $context['api_resource'] = $object;

                return $transformer->transform($object, $outputClass, $context);
            }
        }

        throw new \InvalidArgumentException(sprintf('No output transformer found for resource "%s"', $outputClass));
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        if (!\is_object($data) || is_iterable($data)) {
            return false;
        }

        $class = $context['force_resource_class'] ?? $this->getObjectClass($data);
        $output = $context['output']['class'] ?? $this->getOutputClass($data);
        if ($output && $output !== $class) {
            return true;
        }

        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return $this->decorated->supportsDenormalization($data, $type, $format, $context);
    }

    public function denormalize($data, string $type, ?string $format = null, array $context = []): mixed
    {
        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            'object' => true,
        ];
    }
}
