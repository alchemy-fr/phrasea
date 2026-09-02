<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Entity\Core\Workspace;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class WorkspaceTermsOutput
{
    public function __construct(
        private ?string $text,
        private ?int $version,
        private ?bool $signed,
        private bool $attachToExports,
        private ?string $pdfUrl = null,
    ) {
    }

    #[Groups([Workspace::GROUP_READ])]
    public function getText(): ?string
    {
        return $this->text;
    }

    #[Groups([Workspace::GROUP_READ])]
    public function getVersion(): ?int
    {
        return $this->version;
    }

    /**
     * Whether the current user has signed the current version (null when anonymous or no terms).
     */
    #[Groups([Workspace::GROUP_READ])]
    public function getSigned(): ?bool
    {
        return $this->signed;
    }

    #[Groups([Workspace::GROUP_READ])]
    public function isAttachToExports(): bool
    {
        return $this->attachToExports;
    }

    /**
     * Signed download URL of the provided PDF (null when terms are text-based).
     */
    #[Groups([Workspace::GROUP_READ])]
    public function getPdfUrl(): ?string
    {
        return $this->pdfUrl;
    }
}
