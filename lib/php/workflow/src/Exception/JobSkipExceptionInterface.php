<?php

namespace Alchemy\Workflow\Exception;

interface JobSkipExceptionInterface
{
    public function shouldSkipJob(): bool;
}
