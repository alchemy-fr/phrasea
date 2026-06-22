<?php

namespace App\AdminTask;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class AdminTaskRegistry
{
    public function __construct(
        #[AutowireIterator(tag: AdminTaskInterface::TAG, defaultIndexMethod: 'getName')]
        private iterable $tasks,
    ) {
    }

    public function getTask(string $name): AdminTaskInterface
    {
        return $this->tasks[$name] ?? throw new \InvalidArgumentException(sprintf('Task "%s" not found', $name));
    }
}
