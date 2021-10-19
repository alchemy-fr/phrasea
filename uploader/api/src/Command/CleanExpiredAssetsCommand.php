<?php

declare(strict_types=1);

namespace App\Command;

use App\Storage\SubDefinitionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanExpiredAssetsCommand extends Command
{
    /**
     * @var SubDefinitionManager
     */
    private $assetManager;

    public function __construct(SubDefinitionManager $assetManager)
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
