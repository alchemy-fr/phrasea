<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\File;
use App\Entity\Core\TermsSignature;
use App\Entity\Core\TermsVersion;
use App\Entity\Core\Workspace;
use App\Repository\Core\TermsSignatureRepository;
use App\Repository\Core\TermsVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class TermsManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private TermsVersionRepository $termsVersionRepository,
        private TermsSignatureRepository $termsSignatureRepository,
        private FileStorageManager $fileStorageManager,
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
     * Updates the terms text. Creates a new version when it differs from the
     * current one (the provided PDF, if any, is carried over).
     * An empty string clears the text.
     */
    public function updateTerms(Workspace $workspace, ?string $text): ?TermsVersion
    {
        $text = trim((string) $text);
        $current = $this->termsVersionRepository->getCurrentVersion($workspace);

        if (null !== $current && $text === trim((string) $current->getText())) {
            return $current;
        }

        if (null === $current && '' === $text) {
            return null;
        }

        return $this->createVersion($workspace, $current, $text, $current?->getPdfFile(), $current?->getPdfChecksum());
    }

    /**
     * Sets the terms PDF from an uploaded File (S3 multipart upload).
     * Creates a new version unless the content is identical to the current
     * one (the text is carried over).
     */
    public function setTermsPdfFromFile(Workspace $workspace, File $file): TermsVersion
    {
        $stream = $this->fileStorageManager->getStream($file->getPath());

        try {
            $head = (string) fread($stream, 5);
            if ('%PDF-' !== $head) {
                throw new BadRequestHttpException('Invalid terms PDF: file is not a PDF');
            }

            $ctx = hash_init('sha256');
            hash_update($ctx, $head);
            hash_update_stream($ctx, $stream);
            $checksum = hash_final($ctx);
        } finally {
            fclose($stream);
        }

        $current = $this->termsVersionRepository->getCurrentVersion($workspace);

        if (null !== $current && $checksum === $current->getPdfChecksum()) {
            // Identical content: keep the current version, drop the duplicate upload
            if ($file->getId() !== $current->getPdfFile()?->getId()) {
                $this->fileStorageManager->delete($file->getPath());
                $this->em->remove($file);
            }

            return $current;
        }

        $file->setChecksum($checksum);

        return $this->createVersion($workspace, $current, trim((string) $current?->getText()), $file, $checksum);
    }

    /**
     * Removes the provided PDF: creates a new version without it
     * (the text terms, if any, apply again).
     */
    public function removeTermsPdf(Workspace $workspace): ?TermsVersion
    {
        $current = $this->termsVersionRepository->getCurrentVersion($workspace);
        if (null === $current || !$current->hasPdf()) {
            return $current;
        }

        return $this->createVersion($workspace, $current, trim((string) $current->getText()), null, null);
    }

    public function getPdfContent(TermsVersion $terms): ?string
    {
        if (!$terms->hasPdf()) {
            return null;
        }

        $stream = $this->fileStorageManager->getStream($terms->getPdfFile()->getPath());

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

    private function createVersion(
        Workspace $workspace,
        ?TermsVersion $current,
        string $text,
        ?File $pdfFile,
        ?string $pdfChecksum,
    ): TermsVersion {
        $version = new TermsVersion();
        $version->setWorkspace($workspace);
        $version->setText($text);
        $version->setPdfFile($pdfFile);
        $version->setPdfChecksum($pdfChecksum);
        $version->setVersion(null !== $current ? $current->getVersion() + 1 : 1);
        $this->em->persist($version);

        return $version;
    }
}
