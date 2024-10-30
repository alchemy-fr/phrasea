<?php

namespace Alchemy\Workflow\State\Repository;

interface TransactionalStateRepositoryInterface extends StateRepositoryInterface
{
    public function transactional(callable $callback);
}
