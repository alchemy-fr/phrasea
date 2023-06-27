<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\ValidateFormAction;
use App\Entity\Target;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *     itemOperations={},
 *     collectionOperations={
 *         "validate"={
 *             "method"="POST",
 *             "path"="/form/validate",
 *             "controller"=ValidateFormAction::class,
 *             "description"="Retrieve form schema"
 *         }
 *     }
 * )
 */
final class FormData
{
    /**
     * @var array
     *
     * @Assert\NotNull()
     */
    private $data;

    /**
     * @var Target
     *
     * @Assert\NotNull()
     */
    private $target;

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getTarget(): Target
    {
        return $this->target;
    }

    public function setTarget(Target $target): void
    {
        $this->target = $target;
    }
}
