<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Tag;
use Symfony\Component\Serializer\Annotation\Groups;

class AssetInput
{
    /**
     * @Groups({"asset:write"})
     */
    public ?string $title = null;

    /**
     * @var Tag[]
     * @Groups({"asset:write"})
     */
    public ?array $tags = null;
}
