<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Subscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class UserDirectoryRegistry
{
    public const string TAG = 'alchemy_notifier.user_directory';

    /**
     * @var array<string, UserDirectoryInterface>
     */
    private array $directories = [];

    /**
     * @param iterable<UserDirectoryInterface> $directories
     */
    public function __construct(
        #[AutowireIterator(self::TAG)]
        iterable $directories,
        #[Autowire(param: 'alchemy_notifier.user_directory')]
        private readonly string $defaultDirectory = KeycloakUserDirectory::NAME,
    ) {
        foreach ($directories as $directory) {
            $this->directories[$directory->getName()] = $directory;
        }
    }

    public function get(?string $name): UserDirectoryInterface
    {
        $name ??= $this->defaultDirectory;

        return $this->directories[$name] ?? throw new \InvalidArgumentException(sprintf('Unknown notification user directory "%s". Available: %s.', $name, implode(', ', array_keys($this->directories))));
    }

    public function getDefaultName(): string
    {
        return $this->defaultDirectory;
    }

    /**
     * @return array<string, string> name => label
     */
    public function getChoices(): array
    {
        $choices = [];
        foreach ($this->directories as $name => $directory) {
            $choices[$name] = $directory->getLabel();
        }

        return $choices;
    }
}
