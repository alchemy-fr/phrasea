<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use Doctrine\Persistence\ObjectRepository;

interface AttributeRepositoryInterface extends ObjectRepository
{
    /**
     * @return string[]
     */
    public function getDuplicates(Attribute $attribute): array;

    public function getAssetAttributes(Asset $asset): array;
}
