<?php

declare(strict_types=1);

namespace App\Command;

use App\Documentation\DocumentationGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand('app:documentation:dump')]
class DocumentationDumperCommand extends Command
{
    /** @var array<string, DocumentationGeneratorInterface> */
    private array $chapters = [];

    public function __construct(
        #[AutowireIterator(DocumentationGeneratorInterface::TAG)] private readonly iterable $documentations,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        /** @var DocumentationGeneratorInterface $documentation */
        foreach ($this->documentations as $documentation) {
            $name = $documentation->getName();
            if (isset($this->chapters[$name])) {
                throw new \LogicException(sprintf('Chapter "%s" is already registered.', $name));
            }
            $this->chapters[$name] = $documentation;
        }

        $this
            ->setDescription('Dump code-generated documentation(s)')
            ->addArgument('chapters', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Chapter(s) to dump. If not specified, all chapters will be dumped.')
            ->addOption('output', 'o', InputArgument::OPTIONAL, 'Output directory to write the documentation to. If not specified, it will be written to stdout.')
            ->setHelp(sprintf('chapters: "%s"', join('", "', array_keys($this->chapters))))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputDir = $input->getOption('output');
        if ($outputDir && !is_dir($outputDir)) {
            $output->writeln(sprintf('<error>Output directory "%s" does not exists.</error>', $outputDir));

            return Command::FAILURE;
        }

        foreach ($input->getArgument('chapters') as $chapter) {
            if (!isset($this->chapters[$chapter])) {
                $output->writeln(sprintf('<error>Unknown chapter "%s".</error> Available chapters are "%s"', $chapter, join('", "', array_keys($this->chapters))));

                return Command::FAILURE;
            }
        }

        if (empty($input->getArgument('chapters'))) {
            $input->setArgument('chapters', array_keys($this->chapters));
        }
        foreach ($input->getArgument('chapters') as $chapter) {
            $text = $this->getAsText($this->chapters[$chapter]);
            if ($outputDir) {
                $outputFile = rtrim($outputDir, '/').'/'.$chapter.'.md';
                file_put_contents($outputFile, $text);
                $output->writeln(sprintf('<info>Documentation for chapter "%s" written to "%s".</info>', $chapter, $outputFile));
            } else {
                $output->writeln($text);
            }
        }

        return Command::SUCCESS;
    }

    private function getAsText(DocumentationGeneratorInterface $chapter, array $levels = [1]): string
    {
        $chapter->setLevels($levels);
        $text = '';
        $l = join('.', $levels);
        if (null !== ($t = $chapter->getTitle())) {
            $text .= '# '.$l.': '.$t."\n";
        }
        if (null !== ($t = $chapter->getHeader())) {
            $text .= $t."\n";
        }
        if (null !== ($t = $chapter->getContent())) {
            $text .= $t."\n";
        }

        $n = 1;
        foreach ($chapter->getChildren() as $child) {
            $subLevels = $levels;
            $subLevels[] = $n++;
            $text .= $this->getAsText($child, $subLevels);
        }

        if (null !== ($t = $chapter->getFooter())) {
            $text .= $t."\n";
        }

        return $text;
    }
}
