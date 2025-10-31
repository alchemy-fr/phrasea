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
    public function __construct(
        #[AutowireIterator(DocumentationGeneratorInterface::TAG)] private readonly iterable $documentations,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('destination', InputArgument::REQUIRED, 'Path to the "doc" directory whereto generate documentation')
            ->setDescription('Dump code-generated documentation(s)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $destination = rtrim($input->getArgument('destination'), '/');

        /** @var array<string, DocumentationGeneratorInterface> */
        $chapters = [];

        /** @var DocumentationGeneratorInterface $documentation */
        foreach ($this->documentations as $documentation) {
            $k = $documentation->getPath();
            if (isset($chapters[$k])) {
                throw new \LogicException(sprintf('Chapter "%s" is already registered.', $k));
            }
            $chapters[$k] = $documentation;
        }

        foreach ($chapters as $chapter) {
            $title = $chapter->getTitle() ?? $chapter->getPath();
            $pathParts = pathinfo($chapter->getPath());

            $outputDir = $destination.'/'.$pathParts['dirname'];

            $filename = $pathParts['filename'];
            $extension = $pathParts['extension'];

            @mkdir($outputDir, 0777, true);
            $outputFile = $outputDir.'/'.$filename.'.'.$extension;

            file_put_contents($outputFile, $this->getAsText($chapter, $extension));
            $output->writeln(sprintf('<info>Documentation for chapter "%s" written to "%s".</info>', $title, realpath($outputFile)));
        }

        return Command::SUCCESS;
    }

    private function getAsText(DocumentationGeneratorInterface $chapter, string $extension, array $levels = []): string
    {
        $chapter->setLevels($levels);

        $title = str_replace("'", "''", $chapter->getTitle() ?? $chapter->getPath()); // Escape single quotes for YAML frontmatter
        if (!empty($levels)) {
            $title = join('.', $levels).': '.$title;
        }

        $text = '';
        if (null !== ($t = $chapter->getHeader())) {
            $text .= $t."\n";
        }
        if (null !== ($t = $chapter->getContent())) {
            $text .= $t."\n";
        }

        $n = 1;
        foreach ($chapter->getChildren() as $child) {
            $subLevels = $levels;
            if (!empty($subLevels)) {
                $subLevels[] = $n++;
            }
            $text .= $this->getAsText($child, $extension, $subLevels);
        }

        if (null !== ($t = $chapter->getFooter())) {
            $text .= $t."\n";
        }

        return $text;
    }
}
