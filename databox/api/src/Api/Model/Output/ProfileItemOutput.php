<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Entity\Profile\Profile;
use Symfony\Component\Serializer\Annotation\Groups;

final readonly class ProfileItemOutput
{
    public function __construct(
        #[Groups([Profile::GROUP_READ])]
        public string $id,
        #[Groups([Profile::GROUP_READ])]
        public ?string $definition = null,
        #[Groups([Profile::GROUP_READ])]
        public ?string $key = null,
        #[Groups([Profile::GROUP_READ])]
        public ?int $type = null,
        #[Groups([Profile::GROUP_READ])]
        public ?bool $displayEmpty = null,
        #[Groups([Profile::GROUP_READ])]
        public ?string $format = null,
    ) {
    }
}
