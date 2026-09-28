<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Service\Asset\FileUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class LogoManager
{
    private const array ALLOWED_TYPES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private FileStorageManager $fileStorageManager,
        private FileUrlResolver $fileUrlResolver,
    ) {
    }

    /**
     * Sets the logo from an uploaded File (S3 multipart upload).
     */
    public function setLogo(Workspace $workspace, File $file): void
    {
        if (!in_array($file->getType(), self::ALLOWED_TYPES, true)) {
            throw new BadRequestHttpException(sprintf('Invalid logo type "%s": allowed types are %s', $file->getType(), implode(', ', self::ALLOWED_TYPES)));
        }

        $this->deleteLogoFile($workspace);
        $workspace->setLogoFile($file);
    }

    public function removeLogo(Workspace $workspace): void
    {
        $this->deleteLogoFile($workspace);
        $workspace->setLogoFile(null);
    }

    /**
     * Returns the displayable logo URL, or null when the workspace has no logo.
     */
    public function resolveLogoUrl(Workspace $workspace): ?string
    {
        $file = $workspace->getLogoFile();

        return null !== $file ? $this->fileUrlResolver->resolveUrl($file) : null;
    }

    /**
     * Returns the logo as a data URI (for embedding in generated PDFs), or null.
     */
    public function resolveLogoForPdf(Workspace $workspace): ?string
    {
        $file = $workspace->getLogoFile();
        if (null === $file) {
            return null;
        }

        $stream = $this->fileStorageManager->getStream($file->getPath());
        try {
            return sprintf('data:%s;base64,%s', $file->getType(), base64_encode(stream_get_contents($stream)));
        } finally {
            fclose($stream);
        }
    }

    private function deleteLogoFile(Workspace $workspace): void
    {
        $file = $workspace->getLogoFile();
        if (null === $file) {
            return;
        }

        $workspace->setLogoFile(null);
        if (File::STORAGE_S3_MAIN === $file->getStorage() && $this->fileStorageManager->has($file->getPath())) {
            $this->fileStorageManager->delete($file->getPath());
        }
        $this->em->remove($file);
    }
}
