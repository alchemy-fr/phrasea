<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Config;

class EntityRegistry
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return array{
     *           eventName: string,
     *           event: string,
     *           entityClass: string,
     *           ignoreProperties: string[]
     *         }|null
     */
    public function getConfigNodeForEvent(string $class, string $event): ?array
    {
        $configNode = $this->getConfigNode($class);
        if (null !== $configNode) {
            if ($configNode[$event]['enabled']) {
                $configNode['eventName'] = sprintf('%s:%s', $configNode['name'], $event);
                $configNode['event'] = $event;
                $configNode['entityClass'] = $class;

                return $configNode;
            }
        }

        return null;
    }

    public function getConfigNode(string $class): ?array
    {
        return $this->config[$class] ?? null;
    }
}
