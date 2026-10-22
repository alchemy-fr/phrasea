<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use App\Entity\Core\Workspace;
use App\Service\Image\ImageSetManager;

final readonly class LogoManager
{
    private const int MAX_SIZE = 5 * 1024 * 1024;

    public function __construct(
        private ImageSetManager $imageSetManager,
    ) {
    }

    /**
     * Sets the logo from a completed S3 multipart upload. The previous logo and its files are
     * removed (orphan removal).
     */
    public function setLogo(Workspace $workspace, MultipartUpload $upload): void
    {
        $workspace->setLogo($this->imageSetManager->createFromUpload($upload, $workspace, self::MAX_SIZE));
    }

    public function removeLogo(Workspace $workspace): void
    {
        $workspace->setLogo(null);
    }

    /**
     * Returns the displayable logo URL (a data URI for an SVG), or null when the workspace has no logo.
     */
    public function resolveLogoUrl(Workspace $workspace): ?string
    {
        return $this->imageSetManager->resolveImageUrl($workspace->getLogo());
    }

    /**
     * Returns the logo as a data URI (for embedding in generated PDFs), or null.
     */
    public function resolveLogoForPdf(Workspace $workspace): ?string
    {
        return $this->imageSetManager->resolveDataUri($workspace->getLogo());
    }
}
