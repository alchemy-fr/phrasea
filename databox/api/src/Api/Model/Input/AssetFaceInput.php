<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints\Length;

class AssetFaceInput
{
    /**
     * Name of the person; null or empty removes the identity.
     */
    #[Length(max: 255)]
    public ?string $identity = null;
}
