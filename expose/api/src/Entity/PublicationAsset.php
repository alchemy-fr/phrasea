<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\CreateAssetAction;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="unique_direct_url", columns={"publication_id", "direct_url_path"})})
 * @ApiResource(
 *     iri="http://alchemy.fr/PublicationAsset",
 *     itemOperations={
 *         "get",
 *     },
 *     collectionOperations={
 *         "post"={
 *             "controller"=CreateAssetAction::class,
 *             "defaults"={
 *                  "_api_receive"=false
 *             },
 *             "validation_groups"={"Default", "asset_create"},
 *             "swagger_context"={
 *                 "consumes"={
 *                     "multipart/form-data",
 *                 },
 *                 "parameters"={
 *                     {
 *                         "in"="formData",
 *                         "name"="file",
 *                         "type"="file",
 *                         "description"="The file to upload",
 *                     },
 *                 },
 *             },
 *         }
 *     }
 * )
 */
class PublicationAsset
{
    /**
     * @ApiProperty(identifier=true)
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    protected $id;

    /**
     * @var Publication
     * @Groups({"asset:read"})
     *
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Publication",
     *         }
     *     }
     * )
     * @ORM\ManyToOne(targetEntity="Publication", inversedBy="assets")
     */
    private $publication;

    /**
     * @var Asset
     *
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Asset",
     *         }
     *     }
     * )
     * @Groups({"publication:read"})
     * @ORM\ManyToOne(targetEntity="Asset", inversedBy="publications")
     */
    private $asset;

    /**
     * Direct access to asset
     *
     * @ApiProperty()
     * @Groups({"publication:read", "asset:read"})
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $directUrlPath;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @ApiProperty()
     */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->id = Uuid::uuid4();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    public function getPublication(): Publication
    {
        return $this->publication;
    }

    public function setPublication(Publication $publication): void
    {
        $this->publication = $publication;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getDirectUrlPath(): ?string
    {
        return $this->directUrlPath;
    }

    public function setDirectUrlPath(?string $directUrlPath): void
    {
        $this->directUrlPath = $directUrlPath;
    }
}
