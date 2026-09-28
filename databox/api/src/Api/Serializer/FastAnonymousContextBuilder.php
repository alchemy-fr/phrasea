<?php

declare(strict_types=1);

namespace App\Api\Serializer;

use ApiPlatform\JsonLd\AnonymousContextBuilderInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\Metadata\Util\ClassInfoTrait;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * API Platform builds the full "@context" of an output DTO (iterating every
 * property metadata) for each normalized object, then drops it when the object
 * is a collection member or an embedded relation ("has_context").
 * On a page of 50 assets this happens ~1200 times per request.
 * This decorator short-circuits that case and only emits "@type" / "@id".
 */
#[AsDecorator('api_platform.jsonld.context_builder')]
final class FastAnonymousContextBuilder implements AnonymousContextBuilderInterface
{
    use ClassInfoTrait;

    public function __construct(
        #[AutowireDecorated]
        private readonly AnonymousContextBuilderInterface $decorated,
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    ) {
    }

    public function getBaseContext(int $referenceType = UrlGeneratorInterface::ABS_PATH): array
    {
        return $this->decorated->getBaseContext($referenceType);
    }

    public function getEntrypointContext(int $referenceType = UrlGeneratorInterface::ABS_PATH): array
    {
        return $this->decorated->getEntrypointContext($referenceType);
    }

    public function getResourceContext(string $resourceClass, int $referenceType = UrlGeneratorInterface::ABS_PATH): array
    {
        return $this->decorated->getResourceContext($resourceClass, $referenceType);
    }

    public function getResourceContextUri(string $resourceClass, int $referenceType = UrlGeneratorInterface::ABS_PATH): string
    {
        return $this->decorated->getResourceContextUri($resourceClass, $referenceType);
    }

    public function getAnonymousResourceContext(object $object, array $context = [], int $referenceType = UrlGeneratorInterface::ABS_PATH): array
    {
        if (!($context['has_context'] ?? false)) {
            return $this->decorated->getAnonymousResourceContext($object, $context, $referenceType);
        }

        // Same output as the decorated builder minus the discarded "@context" part.
        $shortName = isset($context['operation'])
            ? $context['operation']->getShortName()
            : (new \ReflectionClass($this->getObjectClass($object)))->getShortName();

        $jsonLdContext = ['@type' => $shortName];

        if (isset($context['iri'])) {
            $jsonLdContext['@id'] = $context['iri'];
        } elseif (true === ($context['gen_id'] ?? true)) {
            $jsonLdContext['@id'] = $this->iriConverter->getIriFromResource($object);
        }

        if (isset($context['api_resource'])) {
            $jsonLdContext['@type'] = $this->resourceMetadataFactory->create($this->getObjectClass($context['api_resource']))[0]->getShortName();
        }

        return $jsonLdContext;
    }
}
