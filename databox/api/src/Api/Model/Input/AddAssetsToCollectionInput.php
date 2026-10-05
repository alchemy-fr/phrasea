<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class AddAssetsToCollectionInput
{
    use IdsInputTrait;

    /**
     * Collection IRI or story Asset IRI.
     */
    #[Assert\NotNull]
    public ?string $destination = null;
}
