<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Api\IriConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use ApiPlatform\Util\OperationRequestInitiatorTrait;
use ApiPlatform\Util\RequestAttributesExtractor;
use App\Api\InputTransformer\InputTransformerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::VIEW, method: 'transform', priority: 255)]
final class InputTransformerListener
{
    use OperationRequestInitiatorTrait;

    /**
     * @var InputTransformerInterface[]
     */
    private readonly iterable $transformers;

    public function __construct(
        #[TaggedIterator('api.input_transformer')]
        iterable $transformers,
        private readonly IriConverterInterface $iriConverter,
        private readonly EntityManagerInterface $em,
    ) {
        $this->transformers = $transformers;
    }

    public function transform(ViewEvent $event): void
    {
        $controllerResult = $event->getControllerResult();
        $request = $event->getRequest();
        if (
            $controllerResult instanceof Response
            || $request->isMethodSafe()
            || !($attributes = RequestAttributesExtractor::extractAttributes($request))
        ) {
            return;
        }

        $operation = $this->initializeOperation($request);
        $input = $controllerResult;
        if (!is_object($input)) {
            return;
        }

        $context = [
            'operation' => $operation,
            'resource_class' => $attributes['resource_class'],
            'previous_data' => $attributes['previous_data'] ?? null,
        ];

        if (is_object($attributes['previous_data'] ?? null)) {
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $this->em->find($attributes['previous_data']::class, $attributes['previous_data']->getId());
            $request->attributes->set('data', $context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        }

        $inputAttr = $operation->getInput();
        if (!$inputAttr || $inputAttr['class'] !== $input::class) {
            return;
        }

        $resourceClass = $operation->getClass();
        foreach ($this->transformers as $transformer) {
            if ($transformer->supports($resourceClass, $input)) {
                $object = $transformer->transform($input, $resourceClass, $context);
                $request->attributes->set('data', $object);
                $event->setControllerResult($object);

                return;
            }
        }

        throw new \InvalidArgumentException(sprintf('No input transformer found for resource "%s"', $input::class));
    }
}
