<?php

declare(strict_types=1);

namespace Alchemy\MetadataManipulatorBundle\Command;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\Metadata\Metadata;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends Command
{
    private MetadataManipulator $mm;

    public function __construct(MetadataManipulator $mm)
    {
        parent::__construct();
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
            ->addArgument('file', InputArgument::OPTIONAL, 'The file (if not set, dump the dictionary)')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, "Dump only infos for Id's matching this regexp, e.g. \"^(XMP|FILE)\"")
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $filter = $input->getOption('filter')) {
            $filter = '';
        }
        $filter = '/'.$filter.'/';

        if ($input->getArgument('file')) {
            $logger = new Logger('PHPExiftool');
            $reader = $this->mm->getReader($logger);
            $reader->files($input->getArgument('file'));
            $metadataBag = $reader->first();

            /** @var Metadata $meta */
            foreach ($metadataBag as $meta) {
                $tagGroup = $meta->getTagGroup();
                $id = $tagGroup->getId();
                if (preg_match($filter, $id)) {
                    $output->writeln(sprintf('<info>%s</info> (name="%s", phpType="%s") ; %s', $id, $tagGroup->getName(), $tagGroup->getPhpType(), $tagGroup->getDescription('en')));
                    $attr = [
                        sprintf('isMulti(): %s', $tagGroup->isMulti() ? 'true' : 'false'),
                        sprintf('isBinary(): %s', $tagGroup->isBinary() ? 'true' : 'false'),
                        sprintf('isWritable(): %s', $tagGroup->isWritable() ? 'true' : 'false'),
                        sprintf('getMaxLength(): %s', $tagGroup->getMaxLength()),
                    ];
                    $output->writeln(sprintf(' attributes: [%s]', join(' ; ', $attr)));

                    $v = $meta->getValue();
                    $output->writeln(sprintf(' value: "%s"', $v->asString()));
                }
            }
        } else {
            // no file arg: dump the dictionary
            foreach ($this->mm->getKnownTagGroups() as $tagGroup) {
                if (preg_match($filter, $tagGroup)) {
                    $output->writeln($tagGroup);
                }
            }
        }

        return 0;
    }
}
