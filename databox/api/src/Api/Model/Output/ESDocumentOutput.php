<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Alchemy\WebhookBundle\Normalizer\WebhookSerializationInterface;
use ApiPlatform\Metadata\ApiProperty;
use App\Api\Filter\Group\GroupValue;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\Share;
use Symfony\Component\Serializer\Annotation\Groups;

final readonly class ESDocumentOutput
{
    public function __construct(
        #[Groups(['_'])]
        private array $data,
    )
    {
    }

    public function getData(): array
    {
        return $this->data;
    }
}
