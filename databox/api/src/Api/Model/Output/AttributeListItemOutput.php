<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Entity\AttributeList\AttributeList;
use Symfony\Component\Serializer\Annotation\Groups;

final readonly class AttributeListItemOutput
{
    public function __construct(
        #[Groups([AttributeList::GROUP_READ])]
        public string $id,
        #[Groups([AttributeList::GROUP_READ])]
        public ?string $definition = null,
        #[Groups([AttributeList::GROUP_READ])]
        public ?string $key = null,
        #[Groups([AttributeList::GROUP_READ])]
        public ?int $type = null,
        #[Groups([AttributeList::GROUP_READ])]
        public ?bool $displayEmpty = null,
        #[Groups([AttributeList::GROUP_READ])]
        public ?string $format = null,
    ) {
    }
}
