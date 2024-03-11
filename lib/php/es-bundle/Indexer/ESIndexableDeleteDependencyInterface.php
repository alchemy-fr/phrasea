<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Indexer;

interface ESIndexableDeleteDependencyInterface extends ESIndexableDependencyInterface
{
    /**
     * @return ESIndexableInterface[]
     */
    public function getIndexableDeleteDependencies(): array;
}
