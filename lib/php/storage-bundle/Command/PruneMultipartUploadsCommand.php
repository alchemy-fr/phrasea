<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Command;

use Alchemy\StorageBundle\Upload\UploadManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PruneMultipartUploadsCommand extends Command
{
    private UploadManager $uploadManager;

    public function __construct(UploadManager $uploadManager)
    {
        parent::__construct();

        $this->uploadManager = $uploadManager;
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('alchemy:storage:prune-multipart-uploads')
            ->setDescription('Removes incomplete multipart uploads parts from S3')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->uploadManager->pruneParts();

        return 0;
    }
}
