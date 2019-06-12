<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\BulkDataAction;
use App\Controller\BulkDataEditAction;

/**
 * @ApiResource(
 *     itemOperations={},
 *     collectionOperations={
 *         "data"={
 *             "method"="GET",
 *             "path"="/bulk-data",
 *             "controller"=BulkDataAction::class,
 *         },
 *         "edit"={
 *             "method"="POST",
 *             "path"="/bulk-data/edit",
 *             "controller"=BulkDataEditAction::class,
 *         },
 *     }
 * )
 */
final class BulkDataModel
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
