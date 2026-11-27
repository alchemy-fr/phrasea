<?php

declare(strict_types=1);

namespace App\Configurator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

final readonly class Configurator
{
    /**
     * @var ConfiguratorInterface[]
     */
    private iterable $configurators;

    public function __construct(
        #[AutowireIterator('app.configurator')]
        iterable $configurators,
    ) {
        $this->configurators = $configurators;
    }

    public function configure(OutputInterface $output, ConfigureContext $context, array $filters = []): void
    {
        foreach ($this->configurators as $configurator) {
            $name = $configurator::getName();
            if (!empty($filters) && !in_array($name, $filters, true)) {
                continue;
            }

            if (!ConfiguratorFlags::isEnabled($name)) {
                $output->writeln(sprintf('Skipping %s configuration (disabled by environment variable)...', $name));

                continue;
            }

            $output->writeln(sprintf('Configuring %s...', $name));

            $retry = 0;
            while (true) {
                try {
                    $this->process($configurator, $output, $context);
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

    private function process(ConfiguratorInterface $configurator, OutputInterface $output, ConfigureContext $context): void
    {
        try {
            $configurator->configure($output, $context);
        } catch (HttpExceptionInterface $e) {
            echo $e->getResponse()->getContent(false);
            throw $e;
        }
    }
}
