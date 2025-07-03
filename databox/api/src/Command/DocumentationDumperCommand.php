<?php

declare(strict_types=1);

namespace App\Command;

use App\documentation\DocumentationGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand('app:documentation:dump')]
class DocumentationDumperCommand extends Command
{
    /**
     * @uses InitialValuesDocumentationGenerator
     * @uses RenditionBuilderDocumentationGenerator
     */

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

        $slugger = new AsciiSlugger();
        /** @var DocumentationGeneratorInterface $documentation */
        foreach ($this->documentations as $documentation) {
            $name = strtolower($slugger->slug($documentation::getName())->toString());
            if (isset($this->chapters[$documentation::getName()])) {
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
            $text = '# '.$this->chapters[$chapter]->getName()."\n".$this->chapters[$chapter]->generate();
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
}
