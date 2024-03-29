<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;

interface AttributeRepositoryInterface extends ObjectRepository
{
    public const LIST_TAG = 'attr_list';

    /**
     * @return string[]
     */
    public function getDuplicates(Attribute $attribute): array;

    public function getAssetAttributes(Asset $asset): array;

    public function getESQueryBuilder(): QueryBuilder;
}
