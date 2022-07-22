<?php

declare(strict_types=1);

namespace Alchemy\MetadataManipulatorBundle\Command;


use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\PHPExiftool;
use PHPExiftool\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use PHPExiftool\Driver\Metadata\Metadata;

class DumpCommand extends Command
{
    private LoggerInterface $logger;
    private MetadataManipulator $mm;

    public function __construct(LoggerInterface $logger, MetadataManipulator $mm)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->mm = $mm;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('alchemy:metadata-manipulator:dump')
            ->setDescription('Dump metadata from a file')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, "Dump only infos for Id's matching this regexp, e.g. \"^(XMP|FILE)\"")
            ->addArgument('file', InputArgument::OPTIONAL, 'The file')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        if(is_null($filter = $input->getOption('filter'))) {
            $filter = '';
        }
        $filter = '/' . $filter . '/';
        /**
         * dump the meta from a file
         */
        if($input->getArgument('file')) {

            $logger = new \Symfony\Bridge\Monolog\Logger("PHPExiftool");
            $reader = $this->mm->getReader($logger);
            $reader->files($input->getArgument('file'));
            $metadataBag = $reader->first();

            /**
             * @var Metadata $meta
             */
            foreach ($metadataBag as $meta) {
                $tag = $meta->getTag();
                $id = $tag->getId();
                if(preg_match($filter, $id)) {
                    $output->writeln(sprintf("<info>%s</info> (name=\"%s\", phpType=\"%s\") ; %s", $id, $tag->getName(), $tag->getPhpType(), $tag->getDescription('en')));
                    $output->write($tag->isMulti() ? " multi" : " mono");
                    $output->write($tag->isBinary() ? " binary" : "");
                    $output->write($tag->isWritable() ? " writable" : " read-only");
                    $output->writeln($tag->getMaxLength() !== 0 ? (" maxl=" . $tag->getMaxLength()) : "");

                    $v = $meta->getValue();
                    $output->writeln(sprintf(" value: \"%s\"", $v->asString()));
                }
            }
        }
        else {
            // no file arg ? dump the dictionnary
            foreach($this->mm->getKnownTagGroups() as $tagGroup) {
                if(preg_match($filter, $tagGroup)) {
                    $output->writeln($tagGroup);
                }
            }
        }
        return 0;
    }
}
