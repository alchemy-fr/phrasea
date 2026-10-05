<?php

declare(strict_types=1);

namespace App\Border\FileAnalyzer\Analyzer;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Border\FileAnalyzer\AbstractAnalyzer;
use App\Border\FileAnalyzer\Dto\AnalysisOutput;
use App\Border\FileAnalyzer\Dto\LogLevelEnum;
use App\Entity\Core\File;
use App\Repository\Core\FileRepository;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Exception\ExceptionInterface as PHPExiftoolExceptionInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class ChecksumAnalyzer extends AbstractAnalyzer
{
    private const string SHA_256 = 'sha256';

    private const string TYPE_DUPLICATE_CHECKSUM = 'duplicate_checksum';

    public function __construct(
        private MetadataManipulator $metadataManipulator,
        private FileRepository $fileRepository,
    ) {
    }

    public static function getName(): string
    {
        return 'checksum';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->scalarNode('algorithm')
                ->defaultValue(self::SHA_256)
                ->info('The hashing algorithm to use.')
            ->end()
            ->booleanNode('stripMetadata')
                ->defaultTrue()
                ->info('Whether to strip metadata from the file before computing the checksum.')
            ->end()
            ->booleanNode('findDuplicates')
                ->defaultTrue()
                ->info('Whether to check for uniqueness of the file based on its checksum.')
            ->end()
            ->integerNode('duplicatesLimit')
                ->info('The maximum number of duplicates to check for. If more than this number of duplicates are found, only the first N will be returned.')
                ->defaultValue(10)
            ->end()
        ;
        // @formatter:on
    }

    public function analyzeFile(File $file, ?string $path, array $config): AnalysisOutput
    {
        $output = new AnalysisOutput();

        $algorithm = $config['algorithm'] ?? self::SHA_256;
        if (empty($path)) {
            throw new \InvalidArgumentException('File path is required for checksum analysis.');
        }
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('File path "%s" does not exist.', $path));
        }

        if ($config['stripMetadata']) {
            try {
                $stripped = $this->generateStrippedMetadataFile($path);
                $checksum = hash_file($algorithm, $stripped);
            } finally {
                if (isset($stripped)) {
                    unlink($stripped);
                }
            }
        } else {
            $checksum = hash_file($algorithm, $path);
        }

        if (self::SHA_256 === $algorithm) {
            $file->setChecksum($checksum);
        }

        if ($config['findDuplicates'] && $config['stripMetadata']) {
            $existingFiles = $this->fileRepository->findDuplicatesByChecksum($file, $config['duplicatesLimit']);
            if (!empty($existingFiles)) {
                foreach ($existingFiles as $duplicateFile) {
                    $output->addDuplicate($duplicateFile->getId());
                }

                $output->addMessage(LogLevelEnum::Critical, self::TYPE_DUPLICATE_CHECKSUM, [
                    'count' => count($existingFiles),
                    'limit' => $config['duplicatesLimit'],
                ]);
            }
        }

        $output->setData([
            'stripped' => $config['stripMetadata'],
            'checksum' => $checksum,
            'algorithm' => $algorithm,
        ]);

        return $output;
    }

    private function generateStrippedMetadataFile(string $path): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'hash');
        unlink($tmpFile);

        $writer = $this->metadataManipulator->createWriter();

        try {
            $writer->reset();
            $writer->erase(true);
            $writer->write($path, new MetadataBag(), $tmpFile);
        } catch (PHPExiftoolExceptionInterface $e) {
            copy($path, $tmpFile);
        }

        return $tmpFile;
    }

    public function requiresFileContent(File $file, array $config): bool
    {
        return true;
    }

    public function validateConfiguration(array $config): void
    {
        $algorithm = $config['algorithm'] ?? self::SHA_256;
        if ($config['findDuplicates'] && self::SHA_256 !== $algorithm) {
            throw new \InvalidArgumentException(sprintf('The findDuplicates option is only supported with the "%s" algorithm.', self::SHA_256));
        }
        if ($config['findDuplicates'] && !$config['stripMetadata']) {
            throw new \InvalidArgumentException(sprintf('The findDuplicates option is only supported when stripMetadata is enabled.'));
        }
    }

    protected function getDocumentationHeader(): string
    {
        return 'This analyzer computes the checksum of a file using a specified hashing algorithm and can check for uniqueness of the file based on its checksum.';
    }
}
