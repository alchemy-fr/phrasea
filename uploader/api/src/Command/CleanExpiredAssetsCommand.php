<?php

declare(strict_types=1);

namespace App\Command;

use App\Storage\AssetManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'app:asset:clean-expired',
    description: 'Remove old assets'
)]
class CleanExpiredAssetsCommand extends Command
{
    public function __construct(private readonly AssetManager $assetManager)
    {
        parent::__construct();
    }

    public function __invoke(
        #[Option('Number of days retention', shortcut: 'r')]
        ?int $daysRetention = null,
    ): int {
        $this->assetManager->cleanAssets($daysRetention ?: null);

        return 0;
    }
}
