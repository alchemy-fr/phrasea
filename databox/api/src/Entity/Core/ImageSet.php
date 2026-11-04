<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An image attached to an owner (entity list value, workspace logo).
 *
 * A raster image holds the optimized version in imageFile and a light thumbnail
 * in thumbnailFile (both built by ProcessImageSetHandler; until then imageFile is
 * the uploaded original). An SVG image only holds its sanitized markup in svgData.
 *
 * An image cropped automatically from a face (the photo of a person, see
 * FaceRecognitionPhotoHandler) keeps the ID of that face in sourceAttributeId; null
 * means a user uploaded the image, which nothing replaces automatically.
 */
#[ORM\Entity]
class ImageSet extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use WorkspaceTrait;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?File $imageFile = null;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?File $thumbnailFile = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $svgData = null;

    /**
     * Not a foreign key: deleting the face must leave the image untouched.
     */
    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $sourceAttributeId = null;

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): void
    {
        $this->imageFile = $imageFile;
    }

    public function getThumbnailFile(): ?File
    {
        return $this->thumbnailFile;
    }

    public function setThumbnailFile(?File $thumbnailFile): void
    {
        $this->thumbnailFile = $thumbnailFile;
    }

    public function getSvgData(): ?string
    {
        return $this->svgData;
    }

    public function setSvgData(?string $svgData): void
    {
        $this->svgData = $svgData;
    }

    public function isSvg(): bool
    {
        return null !== $this->svgData;
    }

    public function getSourceAttributeId(): ?string
    {
        return $this->sourceAttributeId;
    }

    public function setSourceAttributeId(?string $sourceAttributeId): void
    {
        $this->sourceAttributeId = $sourceAttributeId;
    }

    /**
     * Whether the image was cropped automatically from a face (and may be replaced by another one).
     */
    public function isAuto(): bool
    {
        return null !== $this->sourceAttributeId;
    }
}
