<?php

declare(strict_types=1);

namespace App\Configurator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpClient\Exception\ClientException;

final readonly class Configurator
{
    /**
     * @var ConfiguratorInterface[]
     */
    private iterable $configurators;

    public function __construct(
        #[TaggedIterator('app.configurator', defaultPriorityMethod: 'getPriority')]
        iterable $configurators,
    ) {
        $this->configurators = $configurators;
    }

    public function configure(OutputInterface $output, array $presets, array $filters = []): void
    {
        foreach ($this->configurators as $configurator) {
            if (!empty($filters) && !in_array($configurator::getName(), $filters, true)) {
                continue;
            }

            $output->writeln(sprintf('Configuring %s...', $configurator::class));
            try {
                $configurator->configure($output, $presets);
            } catch (ClientException $e) {
                echo $e->getResponse()->getContent(false);
                throw $e;
            }
        }
    }
}
