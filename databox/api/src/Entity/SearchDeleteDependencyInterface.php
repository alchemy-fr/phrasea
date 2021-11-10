<?php

declare(strict_types=1);

namespace App\Entity;

interface SearchDeleteDependencyInterface extends SearchDependencyInterface
{
    /**
     * @return SearchableEntityInterface[]
     */
    public function getSearchDeleteDependencies();
}
