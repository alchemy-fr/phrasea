<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class AssetSourceInput
{
    public ?string $url = null;

    public bool $isPrivate = false;

    public bool $import = false;

    /**
     * Alternative URLs.
     *
     * If path is not accessible publicly, "download" and "open" should be provided with public URI.
     *
     * @var AlternateUrlInput[]
     */
    public ?array $alternateUrls = null;
}
