<?php

declare(strict_types=1);

namespace App\Border\FileAnalyzer\Analyzer;

use App\Border\FileAnalyzer\AbstractAnalyzer;
use App\Border\FileAnalyzer\Dto\AnalysisOutput;
use App\Border\FileAnalyzer\Dto\LogLevelEnum;
use App\Entity\Core\File;
use App\Repository\Core\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class DocUniqueIdAnalyzer extends AbstractAnalyzer
{
    private const string TYPE_DUPLICATE_DOC_UNIQUE_ID = 'duplicate_doc_unique_id';

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
        $output = new AnalysisOutput();

        if (null === $file->getMetadata()) {
            throw new \InvalidArgumentException(sprintf('File %s has no metadata. Please ensure a Readmeta integration is processed before File Analyzer.', $file->getId()));
        }

        $data = [];

        $duid = null;
        foreach ($config['read_from'] as $key) {
            if (!empty($values = $file->getMetadataNameValues($key))) {
                if (1 === count($values)) {
                    $candidate = $this->sanitizeXmpUuid((string) array_first($values));
                    if (Uuid::isValid($candidate)) {
                        $duid = $candidate;
                        $file->setDocUniqueId($duid);
                        $data['found_in'] = $key;
                        break;
                    }
                }
            }
        }

        if (null !== $duid && $config['findDuplicates']) {
            $existingFiles = $this->fileRepository->findDuplicatesByDocUniqueId($file, $config['duplicatesLimit']);
            if (!empty($existingFiles)) {
                foreach ($existingFiles as $f) {
                    $output->addDuplicate($f->getId());
                }

                $output->addMessage(LogLevelEnum::Critical, self::TYPE_DUPLICATE_DOC_UNIQUE_ID, [
                    'count' => count($existingFiles),
                    'limit' => $config['duplicatesLimit'],
                ]);
            }
        }

        if ($config['generate'] && null === $duid) {
            $data['new'] = true;
            $duid = Uuid::uuid4()->toString();
        }

        $data['duid'] = $duid;
        $file->setDocUniqueId($duid);

        if (null !== $duid && $config['write']) {
            foreach ($config['write_to'] as $key) {
                $file->setMetadataValue($key, $duid);
            }
        }

        $this->em->persist($file);

        $output->setData($data);

        return $output;
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
