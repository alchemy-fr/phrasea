<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class ResolveEntitiesInput
{
    /**
     * IRIs of the entities to resolve.
     *
     * @var string[]
     */
    #[Assert\All([
        new Assert\Type('string'),
    ])]
    public array $entities = [];
}
