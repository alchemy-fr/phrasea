<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Entity\Core\Share;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class ShareTermsOutput
{
    public function __construct(
        private ?string $text,
        private int $version,
        private string $workspaceName,
        private ?string $pdfUrl = null,
    ) {
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * Signed download URL of the provided PDF (null when terms are text-based).
     */
    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getPdfUrl(): ?string
    {
        return $this->pdfUrl;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getVersion(): int
    {
        return $this->version;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getWorkspaceName(): string
    {
        return $this->workspaceName;
    }
}
