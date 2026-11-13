<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class AlternateUrlInput
{
    #[Assert\NotBlank]
    public ?string $type = null;

    /**
     * Any URI scheme is accepted (e.g. "indexer:///path/to/file.jpg"), except
     * those that run code when opened by a browser. Clients only open http(s) ones.
     */
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^(?!(?:javascript|vbscript|data):)[a-z][a-z0-9+.\-]*:\S/i',
        message: 'This value is not a valid URL.',
    )]
    public ?string $url = null;
}
