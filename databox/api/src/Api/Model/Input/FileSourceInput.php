<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Util\UrlPattern;
use Symfony\Component\Validator\Constraints as Assert;

class FileSourceInput
{
    /**
     * Served to clients as the file URL: http(s) only.
     * Other schemes go in alternateUrls.
     */
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: UrlPattern::HTTP_URL,
        message: 'This value is not a valid URL.',
    )]
    public ?string $url = null;

    public ?string $originalName = null;
    public ?string $type = null;

    public bool $isPrivate = false;

    public bool $importFile = false;

    /**
     * Alternative URLs.
     *
     * If path is not accessible publicly, "download" and "open" should be provided with public URI.
     *
     * @var AlternateUrlInput[]
     */
    #[Assert\Valid]
    public ?array $alternateUrls = null;
}
