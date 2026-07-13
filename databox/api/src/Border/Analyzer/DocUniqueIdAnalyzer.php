<?php

declare(strict_types=1);

namespace App\Border\Analyzer;

use App\Entity\Core\File;
use App\Repository\Core\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class DocUniqueIdAnalyzer extends AbstractAnalyzer
{
    public function __construct(
        private EntityManagerInterface $em,
        private FileRepository $fileRepository,
    ) {
    }

    public static function getName(): string
    {
        return 'doc_unique_id';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('read_from')
                ->defaultValue([
                    'XMP-exif:ImageUniqueID',
                    'SigmaRaw:ImageUniqueID',
                    'IPTC:UniqueDocumentID',
                    'ExifIFD:ImageUniqueID',
                    'Canon:ImageUniqueID',
                    'XMP-xmpMM:DocumentID',
                ])
                ->scalarPrototype()
                ->end()
            ->end()
            ->booleanNode('write')
                ->defaultTrue()
            ->end()
            ->arrayNode('write_to')
                ->defaultValue([
                    'XMP-exif:ImageUniqueID',
                    'SigmaRaw:ImageUniqueID',
                    'IPTC:UniqueDocumentID',
                    'ExifIFD:ImageUniqueID',
                    'Canon:ImageUniqueID',
                    'XMP-xmpMM:DocumentID',
                ])
                ->scalarPrototype()
                ->end()
            ->end()
            ->booleanNode('findDuplicates')
                ->defaultTrue()
            ->end()
            ->integerNode('duplicatesLimit')
                ->info('The maximum number of duplicates to check for. If more than this number of duplicates are found, only the first N will be returned.')
                ->defaultValue(10)
            ->end()
            ->booleanNode('treatDuplicateAsError')
                ->defaultFalse()
            ->info('Whether to treat duplicate files as errors instead of warnings.')
            ->end()
            ->booleanNode('generate')
                ->defaultTrue()
            ->end()
        ;
        // @formatter:on
    }

    public function analyzeFile(File $file, ?string $path, array $config): AnalysisOutput
    {
        $metadata = $file->getMetadata();
        if (null === $metadata) {
            throw new \InvalidArgumentException(sprintf('File %s has no metadata. Please ensure a Readmeta integration is processed before File Analyzer.', $file->getId()));
        }

        $data = [];

        $duid = null;
        foreach ($config['read_from'] as $key) {
            if (isset($metadata[$key])) {
                $candidate = $this->sanitizeXmpUuid((string) $metadata[$key]);
                if (Uuid::isValid($candidate)) {
                    $duid = $candidate;
                    $data['found_in'] = $key;
                    break;
                }
            }
        }

        $errors = [];
        $warnings = [];

        if ($config['findDuplicates'] && null !== $duid) {
            $existingFiles = $this->fileRepository->findDuplicatesByChecksum($file, $config['duplicatesLimit']);
            if (!empty($existingFiles)) {
                $message = sprintf('%d file(s) with DocUniqueID "%s" already exist.', count($existingFiles), $duid);
                if (count($existingFiles) > $config['duplicatesLimit']) {
                    $message = 'At least '.$message;
                }

                $data['duplicates'] = array_map(static fn (File $file): string => $file->getId(), $existingFiles);

                if ($config['treatDuplicateAsError']) {
                    $errors[] = $message;
                } else {
                    $warnings[] = $message;
                }
            }
        }

        if ($config['generate'] && null === $duid) {
            $data['new'] = true;
            $duid = Uuid::uuid4();
        }

        $data['duid'] = $duid;

        if ($config['write'] && null !== $duid) {
            foreach ($config['write_to'] as $key) {
                $metadata[$key] = (string) $duid;
            }

            $file->setMetadata($metadata);
            $this->em->persist($file);
        }

        return new AnalysisOutput(
            errors: $errors,
            warnings: $warnings,
            data: $data
        );
    }

    private function sanitizeXmpUuid(string $uuid): string
    {
        return str_replace('xmp.did:', '', $uuid);
    }

    public function requiresFileContent(File $file, array $config): bool
    {
        return false;
    }

    public function validateConfiguration(array $config): void
    {
    }

    protected function getDocumentationHeader(): string
    {
        return <<<HEADER
        This analyzer checks for a unique identifier in the file\'s metadata.
        If you enable this analyzer, the `File Analyzer` integration must then need a `Read Metadata` integration to be processed before it.
        HEADER;
    }
}
