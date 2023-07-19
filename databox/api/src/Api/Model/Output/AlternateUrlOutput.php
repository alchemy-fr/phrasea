<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use Symfony\Component\Serializer\Annotation\Groups;

class AlternateUrlOutput extends AbstractUuidOutput
{
    public function __construct(
        #[Groups([File::GROUP_LIST, File::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ])]
        private readonly string $type,
        #[Groups([File::GROUP_LIST, File::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ])]
        private readonly string $url,
        #[Groups([File::GROUP_LIST, File::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ])]
        private readonly ?string $label = null
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }
}
