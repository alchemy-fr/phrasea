<?php

namespace App\Entity\Traits;

use App\Entity\Core\AssetRendition;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait AssetAnnotationsTrait
{
    #[Assert\Collection(
        fields: [
            'type' => new Assert\Choice(AssetAnnotationsInterface::TYPES),
        ],
        allowExtraFields: true,
    )]
    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ])]
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $assetAnnotations = null;

    public function getAssetAnnotations(): ?array
    {
        return $this->assetAnnotations;
    }

    public function setAssetAnnotations(?array $assetAnnotations): void
    {
        $this->assetAnnotations = $assetAnnotations;
    }
}
