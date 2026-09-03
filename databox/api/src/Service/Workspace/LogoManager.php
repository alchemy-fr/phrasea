<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class LogoManager
{
    private const array ALLOWED_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    public function __construct(
        private FileStorageManager $fileStorageManager,
        private UrlSigner $urlSigner,
    ) {
    }

    /**
     * Sets the logo from a file already uploaded to the storage (S3 multipart upload).
     */
    public function setLogo(Workspace $workspace, string $path, ?string $type): void
    {
        if (!isset(self::ALLOWED_TYPES[$type])) {
            throw new BadRequestHttpException(sprintf('Invalid logo type "%s": allowed types are %s', $type, implode(', ', array_keys(self::ALLOWED_TYPES))));
        }

        $this->deleteStoredLogo($workspace);
        $workspace->setLogoPath($path);
    }

    public function removeLogo(Workspace $workspace): void
    {
        $this->deleteStoredLogo($workspace);
        $workspace->setLogoPath(null);
        $workspace->setLogo(null);
    }

    /**
     * Returns the displayable logo URL: signed URL of the uploaded file,
     * or the configured external URL, or null.
     */
    public function resolveLogoUrl(Workspace $workspace): ?string
    {
        $path = $workspace->getLogoPath();
        if (null !== $path) {
            return $this->urlSigner->getSignedUrl($path);
        }

        return $workspace->getLogo();
    }

    /**
     * Returns the uploaded logo as a data URI (for embedding in generated PDFs),
     * or the configured external URL, or null.
     */
    public function resolveLogoForPdf(Workspace $workspace): ?string
    {
        $path = $workspace->getLogoPath();
        if (null === $path) {
            return $workspace->getLogo();
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ('jpeg' === $extension) {
            $extension = 'jpg';
        }
        $type = array_search($extension, self::ALLOWED_TYPES, true) ?: 'image/png';
        $stream = $this->fileStorageManager->getStream($path);
        try {
            return sprintf('data:%s;base64,%s', $type, base64_encode(stream_get_contents($stream)));
        } finally {
            fclose($stream);
        }
    }

    private function deleteStoredLogo(Workspace $workspace): void
    {
        $path = $workspace->getLogoPath();
        if (null !== $path && $this->fileStorageManager->has($path)) {
            $this->fileStorageManager->delete($path);
        }
    }
}
