<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class EditThreadMessageInput
{
    #[Assert\NotBlank]
    public ?string $content = null;
}
