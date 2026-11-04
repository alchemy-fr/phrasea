<?php

declare(strict_types=1);

namespace App\Service\Import;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Api\Processor\InputMapperProcessor;

/**
 * Creates a resource from its input DTO outside of any HTTP request, as its POST operation
 * would: same validation, same mapper, same security expressions (evaluated for the
 * current user, see UserContextSwitcher).
 */
final readonly class ApiInputPersister
{
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private InputMapperProcessor $inputMapperProcessor,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T>      $resourceClass
     * @param array<string, mixed> $context       given to the input mapper
     *
     * @return T
     */
    public function create(string $resourceClass, object $input, array $context = []): object
    {
        $operation = $this->getCreateOperation($resourceClass, $input::class);

        $this->validator->validate($input, $operation->getValidationContext() ?? []);

        $entity = $this->inputMapperProcessor->process($input, $operation, [], [
            'operation' => $operation,
            'resource_class' => $resourceClass,
        ] + $context);
        if (!$entity instanceof $resourceClass) {
            throw new \LogicException(sprintf('Expected a "%s", got "%s"', $resourceClass, get_debug_type($entity)));
        }

        return $entity;
    }

    /**
     * The collection POST operation of the resource, which maps the given input.
     */
    private function getCreateOperation(string $resourceClass, string $inputClass): Operation
    {
        foreach ($this->resourceMetadataCollectionFactory->create($resourceClass) as $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                // e.g. "/assets{._format}", not "/assets/multiple"
                if ($operation instanceof Post
                    && empty($operation->getUriVariables())
                    && !str_contains(ltrim((string) $operation->getUriTemplate(), '/'), '/')
                    && ($operation->getInput()['class'] ?? null) === $inputClass
                ) {
                    return $operation;
                }
            }
        }

        throw new \LogicException(sprintf('No POST operation of "%s" takes a "%s"', $resourceClass, $inputClass));
    }
}
