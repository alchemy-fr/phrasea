<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

use Alchemy\Workflow\State\RecordObject;

final class With extends RecordObject
{
    public function __get(string $name)
    {
        return $this->offsetGet($name);
    }
}
