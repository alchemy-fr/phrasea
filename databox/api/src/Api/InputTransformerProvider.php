<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\InputTransformer\InputTransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[AsDecorator(decorates: 'api_platform.state_provider.deserialize', priority: 1)]
final class InputTransformerProvider implements ProviderInterface
{
    /**
     * @var InputTransformerInterface[]
     */
    private readonly iterable $transformers;

    public function __construct(
        private readonly ProviderInterface $decorated,
        #[TaggedIterator('api.input_transformer')]
        iterable $transformers,
    ) {
        $this->transformers = $transformers;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);
        if ($data instanceof Response) {
            return $data;
        }

        if (!is_object($data)) {
            return $data;
        }

        $resourceClass = $operation->getClass();
        foreach ($this->transformers as $transformer) {
            if ($transformer->supports($resourceClass, $data)) {
                $request = $context['request'];
                if ($previousData = $request?->attributes->get('data')) {
                    $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $previousData;
                }

                return $transformer->transform($data, $resourceClass, $context);
            }
        }

        if (null === $operation->getProcessor()) {
            throw new \InvalidArgumentException(sprintf('No input transformer found for resource "%s"', $data::class));
        }

        return $data;
    }
}
