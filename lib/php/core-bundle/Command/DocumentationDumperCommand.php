<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Command;

use Alchemy\CoreBundle\Documentation\DocumentationGeneratorInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(name: 'alchemy:core:documentation:dump', description: 'Dump code-generated documentation(s)')]
class DocumentationDumperCommand extends Command
{
    public function __construct(
        #[AutowireIterator(DocumentationGeneratorInterface::TAG)]
        private readonly iterable $documentations,
    ) {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('Path to the "doc" directory whereto generate documentation')]
        string $destination,
    ): int {
        $destination = rtrim($destination, '/');

        /** @var array<string, DocumentationGeneratorInterface> $chapters */
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
            $path = $chapter->getPath();
            $pathParts = pathinfo($path);
            $outputDir = $destination.'/'.$pathParts['dirname'];
            $extension = $pathParts['extension'];
            @mkdir($outputDir, 0777, true);
            $outputFile = $outputDir.'/'.$pathParts['filename'].'.'.$extension;
            file_put_contents($outputFile, $this->getAsText($chapter, $extension)."\n");
            $output->writeln(sprintf('<info>Documentation for chapter "%s" written to "%s".</info>', $path, realpath($outputFile)));
        }

        return Command::SUCCESS;
    }

    private function getAsText(DocumentationGeneratorInterface $chapter, string $extension, array $levels = []): string
    {
        $chapter->setLevels($levels);

        $text = '';
        if ($t = trim($chapter->getHeader() ?? '')) {
            $text .= $t."\n\n";
        }
        if ($t = trim($chapter->getContent() ?? '')) {
            $text .= $t."\n\n";
        }

        $n = 1;
        foreach ($chapter->getChildren() as $child) {
            $subLevels = $levels;
            if (!empty($subLevels)) {
                $subLevels[] = $n++;
            }
            $text .= $this->getAsText($child, $extension, $subLevels)."\n\n";
        }

        if ($t = trim($chapter->getFooter() ?? '')) {
            $text .= $t."\n\n";
        }

        return trim($text);
    }
}
