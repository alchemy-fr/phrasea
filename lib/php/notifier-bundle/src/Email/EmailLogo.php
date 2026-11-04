<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Email;

/**
 * The logo of the email header: either hosted elsewhere ($remoteUrl) or held
 * in $content and served by {@see \Alchemy\NotifierBundle\Controller\EmailLogoController}.
 */
final readonly class EmailLogo
{
    public function __construct(
        public bool $custom,
        public ?string $remoteUrl = null,
        public ?string $content = null,
        public ?string $mimeType = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {
    }

    /**
     * Changes with the logo, so that a new logo is not hidden by HTTP caches.
     */
    public function getVersion(): string
    {
        return substr(hash('xxh128', $this->remoteUrl ?? $this->content ?? ''), 0, 16);
    }
}
