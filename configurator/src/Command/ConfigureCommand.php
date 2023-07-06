<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'configure',
    description: 'Configure phrasea stack.',
    hidden: false,
)]
final class ConfigureCommand extends Command
{
    public function __construct()
    {
    }
}
