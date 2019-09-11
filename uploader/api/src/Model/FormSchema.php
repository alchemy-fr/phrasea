<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\FormSchemaAction;
use App\Controller\FormEditSchemaAction;

/**
 * @ApiResource(
 *     itemOperations={},
 *     collectionOperations={
 *         "schema"={
 *             "method"="GET",
 *             "path"="/form-schema",
 *             "controller"=FormSchemaAction::class,
 *         },
 *         "edit"={
 *             "method"="POST",
 *             "path"="/form-schema/edit",
 *             "controller"=FormEditSchemaAction::class,
 *         },
 *     }
 * )
 */
final class FormSchema
{
    /**
     * @var array
     */
    private $schema;

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function setSchema(array $schema): void
    {
        $this->schema = $schema;
    }
}
