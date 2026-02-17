<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Util\EnvHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

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
            $name = $configurator::getName();
            if (!empty($filters) && !in_array($name, $filters, true)) {
                continue;
            }

            if (!EnvHelper::getBooleanEnv('CONFIGURATOR_CONFIGURE_'.strtoupper($name))) {
                $output->writeln(sprintf('Skipping %s configuration (disabled by environment variable)...', $name));

                continue;
            }

            $output->writeln(sprintf('Configuring %s...', $name));

            $retry = 0;
            while (true) {
                try {
                    $this->process($configurator, $output, $presets);
                    break;
                } catch (ExceptionInterface $e) {
                    if ($retry >= 3) {
                        throw new \RuntimeException(sprintf('Failed to configure %s after %d retries: %s', $name, $retry, $e->getMessage()), 0, $e);
                    }
                    $output->writeln(sprintf('Error configuring %s: %s. Retrying...', $name, $e->getMessage()));
                    sleep(1);
                    ++$retry;
                }
            }
        }
    }

    private function process(ConfiguratorInterface $configurator, OutputInterface $output, array $presets): void
    {
        try {
            $configurator->configure($output, $presets);
        } catch (ClientException $e) {
            echo $e->getResponse()->getContent(false);
            throw $e;
        }
    }
}
