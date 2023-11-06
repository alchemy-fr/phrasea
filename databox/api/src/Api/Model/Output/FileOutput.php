<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use Symfony\Component\Serializer\Annotation\Groups;

class FileOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    /**
     * The MIME type.
     */
    #[Groups([File::GROUP_LIST, File::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ, AssetRendition::GROUP_LIST, AssetFileVersion::GROUP_LIST])]
    private ?string $type = null;

    #[Groups([File::GROUP_LIST, File::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ, AssetRendition::GROUP_LIST, AssetFileVersion::GROUP_LIST])]
    private ?int $size = null;

    /**
     * Signed URL.
     */
    #[Groups([File::GROUP_LIST, File::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ, AssetRendition::GROUP_LIST, AssetFileVersion::GROUP_LIST])]
    private ?string $url = null;

    /**
     * @var AlternateUrlOutput[]
     */
    #[Groups([File::GROUP_LIST, File::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ, AssetRendition::GROUP_LIST])]
    private ?array $alternateUrls = [];

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): void
    {
        $this->size = $size;
    }

    /**
     * @return AlternateUrlOutput[]
     */
    public function getAlternateUrls(): ?array
    {
        return $this->alternateUrls;
    }

    public function setAlternateUrls(?array $alternateUrls): void
    {
        $this->alternateUrls = $alternateUrls;
    }
}
