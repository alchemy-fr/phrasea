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
     * Updates the terms text and/or its translations (null = untouched).
     * Creates a new version when the content differs from the current one
     * (the provided PDF, if any, is carried over).
     * An empty text clears the terms.
     */
    public function updateTerms(Workspace $workspace, ?string $text, ?array $textTranslations = null): ?TermsVersion
    {
        $current = $this->termsVersionRepository->getCurrentVersion($workspace);

        $newText = null !== $text ? trim($text) : trim((string) $current?->getText());
        $newTranslations = $textTranslations ?? $current?->getFieldTranslations(TermsVersion::TR_FIELD_TEXT) ?? [];
        $newTranslations = array_filter(array_map(trim(...), $newTranslations));

        if (
            null !== $current
            && $newText === trim((string) $current->getText())
            && $newTranslations === ($current->getFieldTranslations(TermsVersion::TR_FIELD_TEXT) ?: [])
        ) {
            return $current;
        }

        if (null === $current && '' === $newText && empty($newTranslations)) {
            return null;
        }

        return $this->createVersion($workspace, $current, $newText, $newTranslations, $current?->getFile(), $current?->getChecksum());
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

        if (null !== $current && $checksum === $current->getChecksum()) {
            // Identical content: keep the current version, drop the duplicate upload
            if ($file->getId() !== $current->getFile()?->getId()) {
                $this->fileStorageManager->delete($file->getPath());
                $this->em->remove($file);
            }

            return $current;
        }

        $file->setChecksum($checksum);

        return $this->createVersion($workspace, $current, trim((string) $current?->getText()), $current?->getFieldTranslations(TermsVersion::TR_FIELD_TEXT) ?? [], $file, $checksum);
    }

    /**
     * Removes the provided PDF: creates a new version without it
     * (the text terms, if any, apply again).
     */
    public function removeTermsPdf(Workspace $workspace): ?TermsVersion
    {
        $current = $this->termsVersionRepository->getCurrentVersion($workspace);
        if (null === $current || !$current->hasFile()) {
            return $current;
        }

        return $this->createVersion($workspace, $current, trim((string) $current->getText()), $current->getFieldTranslations(TermsVersion::TR_FIELD_TEXT), null, null);
    }

    public function getPdfContent(TermsVersion $terms): ?string
    {
        if (!$terms->hasFile()) {
            return null;
        }

        $stream = $this->fileStorageManager->getStream($terms->getFile()->getPath());

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
        array $textTranslations,
        ?File $file,
        ?string $checksum,
    ): TermsVersion {
        $version = new TermsVersion();
        $version->setWorkspace($workspace);
        $version->setText($text);
        $version->setTranslations($textTranslations ? [TermsVersion::TR_FIELD_TEXT => $textTranslations] : null);
        $version->setFile($file);
        $version->setChecksum($checksum);
        $version->setVersion(null !== $current ? $current->getVersion() + 1 : 1);
        $this->em->persist($version);

        return $version;
    }
}
