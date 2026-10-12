<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Util\ClassInfoTrait;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * Maps resources to their output DTO and remembers the source of each built DTO,
 * so that the DTO is exposed with the IRI and type of its resource.
 */
final class OutputMapperRegistry
{
    use ClassInfoTrait;

    /**
     * @var array<class-string, class-string|null>
     */
    private array $outputClassByClass = [];

    /**
     * @var \WeakMap<object, object>
     */
    private \WeakMap $sources;

    public function __construct(
        #[AutowireLocator(OutputMapperInterface::TAG)]
        private readonly ContainerInterface $mappers,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {
        $this->sources = new \WeakMap();
    }

    /**
     * The output DTO class of a resource, whatever the operation: embedded
     * resources are always exposed through the output of their resource.
     *
     * @return class-string|null
     */
    public function getOutputClass(object $data): ?string
    {
        $class = $this->getObjectClass($data);
        if (array_key_exists($class, $this->outputClassByClass)) {
            return $this->outputClassByClass[$class];
        }

        $outputClass = null;
        foreach ($this->resourceMetadataCollectionFactory->create($class) as $resource) {
            if (null !== $output = $resource->getOutput()['class'] ?? null) {
                $outputClass = $this->mappers->has($output) ? $output : null;
                break;
            }
        }

        return $this->outputClassByClass[$class] = $outputClass;
    }

    public function supports(object $data, string $outputClass): bool
    {
        return !$data instanceof $outputClass
            && $this->mappers->has($outputClass)
            && $this->getMapper($outputClass)->supports($data);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function map(object $data, string $outputClass, array $context = []): object
    {
        $output = $this->getMapper($outputClass)->map($data, $context);
        $this->sources[$output] = $data;

        return $output;
    }

    /**
     * The resource an output DTO was built from.
     */
    public function getSource(object $output): ?object
    {
        return $this->sources[$output] ?? null;
    }

    private function getMapper(string $outputClass): OutputMapperInterface
    {
        return $this->mappers->get($outputClass);
    }
}
