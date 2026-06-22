<?php

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class OperationTaskInput
{
    #[Assert\NotBlank]
    public ?string $name = null;

    public ?array $payload = null;
}
