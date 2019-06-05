<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\ValidateFormAction;
use App\Controller\FormSchemaAction;
use App\Controller\FormEditSchemaAction;

/**
 * @ApiResource(
 *     shortName="form",
 *     itemOperations={},
 *     collectionOperations={
 *         "validate"={
 *             "method"="POST",
 *             "path"="/form/validate",
 *             "controller"=ValidateFormAction::class,
 *             "description"="Retrieve form schema"
 *         },
 *         "schema"={
 *             "method"="GET",
 *             "path"="/form/schema",
 *             "controller"=FormSchemaAction::class,
 *         },
 *         "edit"={
 *             "method"="POST",
 *             "path"="/form/edit",
 *             "controller"=FormEditSchemaAction::class,
 *         },
 *     }
 * )
 */
final class Form
{
    /**
     * @var string
     */
    private $data;

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }
}
