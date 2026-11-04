<?php

declare(strict_types=1);

namespace App\Automation;

use App\Automation\Action\AutomationActionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class AutomationActionRegistry
{
    /**
     * @param ServiceLocator<AutomationActionInterface> $actions
     */
    public function __construct(
        #[AutowireLocator(AutomationActionInterface::TAG)]
        private ServiceLocator $actions,
    ) {
    }

    public function has(string $type): bool
    {
        return $this->actions->has($type);
    }

    public function get(string $type): AutomationActionInterface
    {
        if (!$this->actions->has($type)) {
            throw new \InvalidArgumentException(sprintf('Unsupported automation action "%s"', $type));
        }

        return $this->actions->get($type);
    }

    /**
     * @return string[]
     */
    public function getTypes(): array
    {
        return array_keys($this->actions->getProvidedServices());
    }
}
