<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\MultipartUploadPartAction;
use App\Controller\MultipartUploadStartAction;
use App\Controller\MultipartUploadCancelAction;

/**
 * @ApiResource(
 *     collectionOperations={
 *         "post"={
 *             "controller"=MultipartUploadStartAction::class,
 *         },
 *     },
 *     itemOperations={
 *         "part"={
 *             "controller"=MultipartUploadPartAction::class,
 *         },
 *         "cancel"={
 *             "controller"=MultipartUploadCancelAction::class,
 *         },
 *     }
 * )
 */
class MultipartUpload
{
    private string $filename;
    private string $type;
}
