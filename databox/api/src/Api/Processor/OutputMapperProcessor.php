<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\SerializerContextBuilderInterface;
use App\Api\Mapper\Output\OutputMapperRegistry;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Api\Provider\MappedPaginator;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maps the resource(s) returned by the provider or the processor of an operation
 * to the operation output DTO, once written and before serialization.
 */
#[AsDecorator('api_platform.state_processor.main', priority: 140)]
final readonly class OutputMapperProcessor implements ProcessorInterface
{
    public function __construct(
        #[AutowireDecorated]
        private ProcessorInterface $decorated,
        private OutputMapperRegistry $outputMapperRegistry,
        #[Autowire(service: 'api_platform.serializer.context_builder')]
        private SerializerContextBuilderInterface $serializerContextBuilder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $outputClass = $operation->getOutput()['class'] ?? null;
        $request = $context['request'] ?? null;

        if (null !== $outputClass
            && null !== $request
            && null !== $data
            && !$data instanceof Response
            && false !== $operation->canSerialize()
        ) {
            $normalizationContext = $this->serializerContextBuilder->createFromRequest($request, true, [
                'resource_class' => $operation->getClass(),
                'operation' => $operation,
            ]);

            $data = $this->mapData($data, $outputClass, $normalizationContext);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    private function mapData(mixed $data, string $outputClass, array $context): mixed
    {
        if ($data instanceof ApiMetaWrapperOutput) {
            return $data->withResult($this->mapData($data->getResult(), $outputClass, $context));
        }

        if ($data instanceof PaginatorInterface) {
            return new MappedPaginator($data, fn (mixed $item): mixed => \is_object($item) ? $this->mapItem($item, $outputClass, $context) : $item);
        }

        if (\is_array($data)) {
            return array_map(fn (mixed $item): mixed => \is_object($item) ? $this->mapItem($item, $outputClass, $context) : $item, $data);
        }

        if (\is_object($data) && !$data instanceof \Traversable) {
            return $this->mapItem($data, $outputClass, $context);
        }

        return $data;
    }

    private function mapItem(object $item, string $outputClass, array $context): object
    {
        if (!$this->outputMapperRegistry->supports($item, $outputClass)) {
            return $item;
        }

        return $this->outputMapperRegistry->map($item, $outputClass, $context);
    }
}
