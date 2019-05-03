<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\DownloadUrlAction;

/**
 * @ApiResource(
 *     shortName="download",
 *     collectionOperations={
 *         "post"={
 *             "controller"=DownloadUrlAction::class,
 *         },
 *     },
 *     itemOperations={}
 * )
 */
class DownloadUrl
{
    /**
     * @var string
     */
    private $url;

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
}
