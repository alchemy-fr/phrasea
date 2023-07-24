<?php

declare(strict_types=1);

namespace App\Api\Serializer\Normalizer;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Util\ClassInfoTrait;
use ApiPlatform\Serializer\InputOutputMetadataTrait;
use App\Api\DtoTransformer\OutputTransformerInterface;
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
     * @var OutputTransformerInterface[]
     */
    private readonly iterable $transformers;

    public function __construct(
        private readonly NormalizerInterface $decorated,
        #[TaggedIterator('api.output_transformer')]
        iterable $transformers,
        ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null,
    )
    {
        $this->transformers = $transformers;
        $this->resourceMetadataCollectionFactory = $resourceMetadataCollectionFactory;
    }

    public function normalize(mixed $object, string $format = null, array $context = [])
    {
        if (is_object($object) && !is_iterable($object)) {
            if (null !== $outputClass = $this->getOutputClass($object)) {
                $context['output']['class'] = $outputClass;
                $context['real_resource_class'] = [
                    'class' => $this->getObjectClass($object),
                    'output' => $outputClass,
                ];

                return $this->decorated->normalize($this->transform($object, $outputClass, $context), $format, $context);
            }
        }

        if (isset($context['real_resource_class']) && isset($context['resource_class'])) {
            if ($context['real_resource_class']['output'] === $context['resource_class']) {
                $context['resource_class'] = $context['real_resource_class']['class'];
                unset($context['real_resource_class']);
            }
        }

        return $this->decorated->normalize($object, $format, $context);
    }

    private function getOutputClass(object $object): ?string
    {
        $metadata = $this->resourceMetadataCollectionFactory->create($this->getObjectClass($object));
        foreach ($metadata as $m) {
            if (null !== $output = $m->getOutput()) {
                return $output['class'];
            }
        }

        return null;
    }

    private function transform(object $object, string $outputClass, array $context): object
    {
        foreach ($this->transformers as $transformer) {
            if ($transformer->supports($outputClass, $object)) {
                return $transformer->transform($object, $outputClass, $context);
            }
        }

        throw new \InvalidArgumentException(sprintf('No transformer found for resource "%s"', $outputClass));
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }

    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer)
    {
        if($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }
}
