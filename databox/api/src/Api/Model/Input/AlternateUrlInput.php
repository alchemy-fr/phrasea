<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class AlternateUrlInput
{
    #[Assert\NotBlank]
    public ?string $type = null;

    #[Assert\NotBlank]
    #[Assert\Url(requireTld: false)]
    public ?string $url = null;
}
