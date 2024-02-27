<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Indexer;

interface SearchDeleteDependencyInterface extends SearchDependencyInterface
{
    /**
     * @return ESIndexableInterface[]
     */
    public function getSearchDeleteDependencies(): array;
}
