<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

final readonly class BuiltInAttributeRegistry
{
    public function __construct(
        #[TaggedLocator(BuiltInAttributeInterface::TAG, defaultIndexMethod: 'getKey')]
        private ServiceProviderInterface $items,
    ) {
    }

    public function getBuiltInField(string $key): ?BuiltInAttributeInterface
    {
        if (!str_starts_with($key, '@')) {
            return null;
        }

        if ($this->items->has($key)) {
            return $this->items->get($key);
        }

        return null;
    }

    /**
     * @return BuiltInAttributeInterface[]
     */
    public function getAll(): iterable
    {
        foreach ($this->items->getProvidedServices() as $id => $class) {
            yield $this->items->get($id);
        }
    }
}
