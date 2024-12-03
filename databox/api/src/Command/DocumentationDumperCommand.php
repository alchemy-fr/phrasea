<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Alchemy\RenditionFactory\DocumentationDumper as RenditionFactoryDocumentationDumper;


#[AsCommand('app:documentation:dump')]
class DocumentationDumperCommand extends Command
{
    public function __construct(
        private readonly RenditionFactoryDocumentationDumper $renditionFactoryDocumentationDumper,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:documentation:dump')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('# rendition factory');
        $output->writeln($this->renditionFactoryDocumentationDumper->dump());

        return 0;
    }
}
