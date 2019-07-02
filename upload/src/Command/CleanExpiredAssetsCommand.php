<?php

declare(strict_types=1);

namespace App\Command;

use App\Storage\AssetManager;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CleanExpiredAssetsCommand extends Command
{
    /**
     * @var AssetManager
     */
    private $assetManager;

    public function __construct(AssetManager $assetManager)
    {
        parent::__construct();
        $this->assetManager = $assetManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:asset:clean-expired')
            ->setDescription('Remove old assets')
            ->addOption('days-retention', 'r', InputOption::VALUE_REQUIRED, 'Number of days retention')
            ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $assetDaysRetention = $input->getOption('days-retention');

        $this->assetManager->cleanAssets($assetDaysRetention ? (int) $assetDaysRetention : null);

        return 0;
    }
}
