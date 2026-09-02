<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use App\Entity\Core\TermsSignature;
use App\Entity\Core\TermsVersion;
use App\Entity\Core\Workspace;
use App\Repository\Core\TermsSignatureRepository;
use App\Repository\Core\TermsVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class TermsManager
{
    private const string PDF_DATA_URI_PREFIX = 'data:application/pdf;base64,';

    public function __construct(
        private EntityManagerInterface $em,
        private TermsVersionRepository $termsVersionRepository,
        private TermsSignatureRepository $termsSignatureRepository,
        private FileStorageManager $fileStorageManager,
        private PathGeneratorInterface $pathGenerator,
    ) {
    }

    /**
     * Returns the current terms of the workspace, or null when the workspace has none
     * (never defined, or cleared by an empty version).
     */
    public function getCurrentTerms(Workspace $workspace): ?TermsVersion
    {
        $version = $this->termsVersionRepository->getCurrentVersion($workspace);
        if (null === $version || $version->isEmpty()) {
            return null;
        }

        return $version;
    }

    /**
     * Creates a new version when the content differs from the current one.
     * Previous versions are kept so past signatures stay bound to the content they signed.
     *
     * $text: null = untouched, empty string = cleared.
     * $pdfDataUri: null = untouched, empty string = removed, otherwise a
     * "data:application/pdf;base64," data URI stored as the provided PDF.
     */
    public function updateTerms(Workspace $workspace, ?string $text, ?string $pdfDataUri = null): ?TermsVersion
    {
        $current = $this->termsVersionRepository->getCurrentVersion($workspace);

        $newText = null !== $text ? trim($text) : trim((string) $current?->getText());

        $newPdfBinary = null;
        if (null === $pdfDataUri) {
            $newPdfPath = $current?->getPdfPath();
            $newPdfChecksum = $current?->getPdfChecksum();
        } elseif ('' === $pdfDataUri) {
            $newPdfPath = null;
            $newPdfChecksum = null;
        } else {
            $newPdfBinary = $this->decodePdfDataUri($pdfDataUri);
            $newPdfChecksum = hash('sha256', $newPdfBinary);
            $newPdfPath = $current?->getPdfChecksum() === $newPdfChecksum ? $current->getPdfPath() : null;
        }

        if (
            null !== $current
            && $newText === trim((string) $current->getText())
            && $newPdfChecksum === $current->getPdfChecksum()
        ) {
            return $current;
        }

        if (null === $current && '' === $newText && null === $newPdfChecksum) {
            return null;
        }

        if (null !== $newPdfBinary && null === $newPdfPath) {
            $newPdfPath = $this->pathGenerator->generatePath('pdf', 'terms/');
            $this->fileStorageManager->store($newPdfPath, $newPdfBinary);
        }

        $version = new TermsVersion();
        $version->setWorkspace($workspace);
        $version->setText($newText);
        $version->setPdfPath($newPdfPath);
        $version->setPdfChecksum($newPdfChecksum);
        $version->setVersion(null !== $current ? $current->getVersion() + 1 : 1);
        $this->em->persist($version);

        return $version;
    }

    public function getPdfContent(TermsVersion $terms): ?string
    {
        if (!$terms->hasPdf()) {
            return null;
        }

        $stream = $this->fileStorageManager->getStream($terms->getPdfPath());

        try {
            return stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    public function hasSigned(TermsVersion $termsVersion, string $userId): bool
    {
        return null !== $this->termsSignatureRepository->findSignature($termsVersion, $userId);
    }

    public function sign(TermsVersion $termsVersion, string $userId): TermsSignature
    {
        $signature = $this->termsSignatureRepository->findSignature($termsVersion, $userId);
        if (null !== $signature) {
            return $signature;
        }

        $signature = new TermsSignature();
        $signature->setTermsVersion($termsVersion);
        $signature->setUserId($userId);
        $this->em->persist($signature);
        $this->em->flush();

        return $signature;
    }

    private function decodePdfDataUri(string $dataUri): string
    {
        if (!str_starts_with($dataUri, self::PDF_DATA_URI_PREFIX)) {
            throw new BadRequestHttpException('Invalid terms PDF: expected a "data:application/pdf;base64," data URI');
        }

        $binary = base64_decode(substr($dataUri, strlen(self::PDF_DATA_URI_PREFIX)), true);
        if (false === $binary || '' === $binary) {
            throw new BadRequestHttpException('Invalid terms PDF: malformed base64 content');
        }

        if (!str_starts_with($binary, '%PDF-')) {
            throw new BadRequestHttpException('Invalid terms PDF: file is not a PDF');
        }

        return $binary;
    }
}
