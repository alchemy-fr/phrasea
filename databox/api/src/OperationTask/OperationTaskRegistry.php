<?php

namespace App\OperationTask;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

final readonly class OperationTaskRegistry
{
    public function __construct(
        #[AutowireLocator(services: OperationTaskInterface::TAG, defaultIndexMethod: 'getName')]
        private ContainerInterface $tasks,
    ) {
    }

    public function getTask(string $name): OperationTaskInterface
    {
        if (!$this->tasks->has($name)) {
            throw new \InvalidArgumentException(sprintf('Task "%s" not found', $name));
        }

        return $this->tasks->get($name);
    }
}
