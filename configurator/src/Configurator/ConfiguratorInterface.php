<?php

declare(strict_types=1);

namespace App\Configurator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.configurator')]
interface ConfiguratorInterface
{
    public function configure(OutputInterface $output): void;
}
