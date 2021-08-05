<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Command;

use App\Consumer\Handler\Notify\RegisterUserToNotifierHandler;
use Alchemy\StorageBundle\Upload\UploadManager;
use App\User\UserManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PruneMultipartUploadsCommand extends Command
{
    private UploadManager $uploadManager;

    public function __construct(UploadManager $uploadManager)
    {
        parent::__construct();

        $this->uploadManager = $uploadManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('alchemy:storage:prune-multipart-uploads')
            ->setDescription('Removes incomplete multipart uploads parts from S3')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->uploadManager->pruneParts();

        return 0;
    }
}
