<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\ValidateFormAction;

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
     */
    private $data;

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
