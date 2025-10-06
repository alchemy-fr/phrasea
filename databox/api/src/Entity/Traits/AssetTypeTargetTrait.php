<?php

namespace App\Entity\Traits;

use App\Model\AssetTypeEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait AssetTypeTargetTrait
{
    #[ORM\Column(type: Types::SMALLINT, nullable: false, enumType: AssetTypeEnum::class)]
    protected AssetTypeEnum $target = AssetTypeEnum::Asset;

    public function getTarget(): AssetTypeEnum
    {
        return $this->target;
    }

    public function isForTarget(AssetTypeEnum $target): bool
    {
        return ($this->target->value & $target->value) === $target->value;
    }

    public function setTarget(AssetTypeEnum $target): void
    {
        $this->target = $target;
    }
}
