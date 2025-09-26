<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

final readonly class BuiltInFieldRegistry
{
    public function __construct(
        #[TaggedLocator(BuiltInFieldInterface::TAG, defaultIndexMethod: 'getKey')]
        private ServiceProviderInterface $items,
    ) {
    }

    public function getBuiltInField(string $key): ?BuiltInFieldInterface
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
     * @return BuiltInFieldInterface[]
     */
    public function getAll(): iterable
    {
        foreach ($this->items->getProvidedServices() as $id => $class) {
            yield $this->items->get($id);
        }
    }
}
