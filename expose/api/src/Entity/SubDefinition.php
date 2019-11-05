<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Controller\CreateSubDefinitionAction;

/**
 * @ORM\Entity(repositoryClass="App\Repository\SubDefinitionRepository")
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="uniq_asset_type",columns={"asset_id", "name"})})
 * @ApiResource(
 *     normalizationContext=SubDefinition::API_READ,
 *     itemOperations={
 *          "get"={}
 *     },
 *     collectionOperations={
 *          "post"= {
 *             "controller"=CreateSubDefinitionAction::class,
 *             "defaults"={
 *                  "_api_receive"=false
 *             },
 *             "validation_groups"={"Default", "subdef_create"},
 *             "swagger_context"={
 *                 "summary"="Upload asset sub-definition",
 *                 "consumes"={
 *                     "multipart/form-data",
 *                 },
 *                 "parameters"={
 *                     {
 *                         "in"="formData",
 *                         "name"="file",
 *                         "type"="file",
 *                         "required"=true,
 *                         "description"="The file to upload",
 *                     },
 *                     {
 *                         "in"="formData",
 *                         "name"="asset_id",
 *                         "required"=true,
 *                         "type"="string",
 *                         "description"="The owning asset ID",
 *                     },
 *                     {
 *                         "in"="formData",
 *                         "name"="name",
 *                         "required"=true,
 *                         "type"="string",
 *                         "description"="The sub definition name",
 *                     },
 *                     {
 *                         "in"="formData",
 *                         "name"="use_as_preview",
 *                         "type"="bool",
 *                         "description"="The sub definition will be used as a gallery preview",
 *                     },
 *                     {
 *                         "in"="formData",
 *                         "name"="use_as_thumbnail",
 *                         "type"="bool",
 *                         "description"="The sub definition will be used as a thumbnail",
 *                     },
 *                     {
 *                         "in"="body",
 *                     },
 *                 },
 *             },
 *          },
 *     }
 *  )
 */
class SubDefinition implements MediaInterface
{
    const API_READ = [
        'groups' => ['subdef:read'],
        'swagger_definition_name' => 'Read',
    ];

    /**
     * @ApiProperty(identifier=true)
     * @Groups({"asset:read", "publication:read", "subdef:read"})
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    protected $id;

    /**
     * @var Asset
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Asset", inversedBy="subDefinitions")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $asset;

    /**
     * @var string
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read", "subdef:read"})
     * @ORM\Column(type="string", length=30)
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $path;

    /**
     * @var int
     * @Groups({"subdef:read", "publication:read"})
     * @ORM\Column(type="integer")
     */
    private $size;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @ApiProperty()
     * @Groups({"subdef:read", "asset:read"})
     */
    private $mimeType;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @ApiProperty()
     * @Groups({"subdef:read"})
     */
    private $createdAt;

    /**
     * @ApiProperty()
     * @Groups({"subdef:read", "asset:read", "publication:read"})
     * @var string
     */
    private $url;

    /**
     * @ApiProperty()
     * @Groups({"subdef:read", "asset:read", "publication:read"})
     * @var string
     */
    private $downloadUrl;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->id = Uuid::uuid4();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getDownloadUrl(): string
    {
        return $this->downloadUrl;
    }

    public function setDownloadUrl(string $downloadUrl): void
    {
        $this->downloadUrl = $downloadUrl;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
