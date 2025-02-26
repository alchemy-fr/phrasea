<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Traits;

use Symfony\Component\Serializer\Attribute\Groups;

trait ExtraMetadataDTOTrait
{
    #[Groups(['_'])]
    protected ?array $extraMetadata = null;

    public function getExtraMetadata(): ?array
    {
        return $this->extraMetadata;
    }

    public function setExtraMetadata(?array $extraMetadata): void
    {
        $this->extraMetadata = $extraMetadata;
    }
}
