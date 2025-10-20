<?php

namespace App\Border\Analyzer;

use App\Entity\Core\File;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ChecksumAnalyzer implements AnalyzerInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public static function getName(): string
    {
        return 'checksum';
    }

    public function analyzeFile(File $file, ?string $path, array $config): AnalysisOutput
    {
        $algorithm = $config['algorithm'] ?? 'sha256';
        if (empty($path)) {
            return new AnalysisOutput(
                errors: ['File path is required for checksum analysis.']
            );
        }
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('File path "%s" does not exist.', $path));
        }
        $checksum = hash_file($algorithm, $path);

        if ('sha256' === $algorithm) {
            $file->setChecksum($checksum);
        }

        $errors = [];
        $warnings = [];

        if ($config['unique'] ?? false) {
            $existingFile = $this->em->getRepository(File::class)->findOneBy(['checksum' => $checksum]);
            if ($existingFile && $existingFile->getId() !== $file->getId()) {
                $message = sprintf('A file with checksum "%s" already exists (File ID: %d).', $checksum, $existingFile->getId());

                if ($config['treatDuplicateAsError'] ?? false) {
                    $errors[] = $message;
                } else {
                    $warnings[] = $message;
                }
            }
        }

        $data = [
            'checksum' => $checksum,
            'algorithm' => $algorithm,
        ];

        return new AnalysisOutput(
            errors: $errors,
            warnings: $warnings,
            data: $data
        );
    }

    public function requiresFileContent(File $file, array $config): bool
    {
        return true;
    }
}
