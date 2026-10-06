<?php

declare(strict_types=1);

namespace App\Api\Serializer;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use App\Api\Mapper\Output\OutputMapperRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * An output DTO is identified by the IRI of the resource it was built from.
 */
#[AsDecorator('api_platform.symfony.iri_converter')]
final readonly class OutputIriConverter implements IriConverterInterface
{
    public function __construct(
        #[AutowireDecorated]
        private IriConverterInterface $decorated,
        private OutputMapperRegistry $outputMapperRegistry,
    ) {
    }

    public function getResourceFromIri(string $iri, array $context = [], ?Operation $operation = null): object
    {
        return $this->decorated->getResourceFromIri($iri, $context, $operation);
    }

    public function getIriFromResource(object|string $resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null, array $context = []): ?string
    {
        if (\is_object($resource) && null !== $source = $this->outputMapperRegistry->getSource($resource)) {
            return $this->decorated->getIriFromResource($source, $referenceType);
        }

        return $this->decorated->getIriFromResource($resource, $referenceType, $operation, $context);
    }
}
