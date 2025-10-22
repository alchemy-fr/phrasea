<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class FileSourceInput
{
    #[Assert\NotBlank]
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
    public ?array $alternateUrls = null;
}
