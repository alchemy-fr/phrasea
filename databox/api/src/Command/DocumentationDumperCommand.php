<?php

declare(strict_types=1);

namespace App\Command;

use App\Documentation\DocumentationGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
            $k = $documentation->getPath();
            if (isset($this->chapters[$k])) {
                throw new \LogicException(sprintf('Chapter "%s" is already registered.', $k));
            }
            $this->chapters[$k] = $documentation;
        }

        $this
            ->setDescription('Dump code-generated documentation(s)')
            ->setHelp(sprintf('chapters: <info>%s</info>', join('</info> ; <info>', array_keys($this->chapters))))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->chapters as $chapter) {
            $title = $chapter->getTitle() ?? $chapter->getPath();
            $pathParts = pathinfo($chapter->getPath());

            if ('/' === substr($pathParts['dirname'], 0, 1)) {
                // getPath() returned a "absolute" path, which will be relative to the phrasea project root
                $outputDir = __DIR__.'/../../../..'.$pathParts['dirname'];
            } else {
                // getPath() returned a "relative" path, which will be relative to the databox/api folder
                $outputDir = __DIR__.'/../../'.$pathParts['dirname'];
            }
            $filename = $pathParts['filename'];
            $extension = $pathParts['extension'];
            if (!in_array($extension, ['md', 'xml', 'json'], true)) {
                $output->writeln(sprintf('<error>Chapter "%s" must have a [md | xml | json] extension, found "%s".</error>', $title, $extension));
                continue;
            }

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
