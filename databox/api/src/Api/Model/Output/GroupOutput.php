<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Annotation\Groups;

final class GroupOutput
{
    #[Groups(['_'])]
    public ?string $id = null;

    #[Groups(['_'])]
    public ?string $name = null;
}
