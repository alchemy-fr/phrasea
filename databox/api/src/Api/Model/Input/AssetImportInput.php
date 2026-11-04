<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use App\Entity\Core\AssetImport;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Symfony\Component\Validator\Constraints as Assert;

class AssetImportInput
{
    #[Assert\NotNull]
    public ?Workspace $workspace = null;

    /**
     * The default destination of the entries (the workspace root otherwise).
     */
    public ?Collection $collection = null;

    #[Assert\NotNull]
    #[Assert\Choice(choices: AssetImport::FORMATS)]
    public ?string $format = null;

    /**
     * The privacy of the entries which do not set one.
     */
    public ?int $privacy = null;

    #[Assert\Length(max: 255)]
    public ?string $originalName = null;

    /**
     * The instructions file, uploaded to the storage. Or provide "data".
     */
    #[Assert\Valid]
    public ?MultipartUploadInput $multipart = null;

    /**
     * The instructions file content. Or provide "multipart".
     */
    public ?string $data = null;
}
