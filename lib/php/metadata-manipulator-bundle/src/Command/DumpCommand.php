<?php

declare(strict_types=1);

namespace Alchemy\MetadataManipulatorBundle\Command;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\Metadata\Metadata;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'alchemy:metadata-manipulator:dump',
    description: 'Dump metadata from a file'
)]
class DumpCommand extends Command
{
    public function __construct(private readonly MetadataManipulator $mm)
    {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('The file (if not set, dump the dictionary)')]
        ?string $file = null,
        #[Option('Dump only infos for Id\'s matching this regexp, e.g. "^(XMP|FILE)"')]
        string $filter = '',
    ): int {
        $filter = '/'.$filter.'/';

        if ($file) {
            $logger = new Logger('PHPExiftool');
            $reader = $this->mm->getReader($logger);
            $reader->files($file);
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
                if (preg_match($filter, (string) $tagGroup)) {
                    $output->writeln($tagGroup);
                }
            }
        }

        return 0;
    }
}
