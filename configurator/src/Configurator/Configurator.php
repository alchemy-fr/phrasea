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
        #[TaggedIterator('app.configurator')]
        iterable $configurators,
    ) {
        $this->configurators = $configurators;
    }

    public function configure(OutputInterface $output, array $presets): void
    {
        foreach ($this->configurators as $configurator) {
            $output->writeln(sprintf('Configuring %s...', $configurator::class));
            try {
                $configurator->configure($output, $presets);
            } catch (ClientException $e) {
                dump($e->getResponse()->getContent(false));
                throw $e;
            }
        }
    }
}
