<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class OAuthClientInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9._-]{1,255}$/',
        message: 'The client ID can only contain letters, digits, ".", "_" and "-" (255 characters at most).',
    )]
    public ?string $clientId = null;

    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Length(max: 1000)]
    public ?string $description = null;
}
