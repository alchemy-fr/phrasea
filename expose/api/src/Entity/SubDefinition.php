<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\CreateSubDefinitionAction;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

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
 *                         "description"="The file to upload [required if no upload payload is provided]",
 *                     },
 *                     {
 *                         "in"="formData",
 *                         "name"="upload",
 *                         "type"="object",
 *                         "required"=false,
 *                         "description"="The upload payload [required if no file is uploaded]. When provided, you receive a signed URL for uploading the file.
Available options:
 * type - the file MIME type (required)
 * name - the original client name (optional)
 * size - the file size (defaults to 0 if not provided)
",
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
    const THUMBNAIL = 'thumbnail';
    const PREVIEW = 'preview';

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
     * @ORM\ManyToOne(targetEntity="App\Entity\Asset", inversedBy="subDefinitions")
     * @ORM\JoinColumn(nullable=false)
     */
    protected ?Asset $asset = null;

    /**
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read", "subdef:read"})
     * @ORM\Column(type="string", length=30)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $path = null;

    /**
     * @Groups({"subdef:read", "publication:read"})
     * @ORM\Column(type="bigint", options={"unsigned"=true})
     */
    private ?int $size = null;

    /**
     * @ORM\Column(type="string", length=255)
     * @ApiProperty()
     * @Groups({"subdef:read", "asset:read"})
     */
    private ?string $mimeType = null;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @ApiProperty()
     * @Groups({"subdef:read"})
     */
    private ?DateTime $createdAt = null;

    /**
     * @ApiProperty()
     * @Groups({"subdef:read", "asset:read", "publication:read"})
     */
    private ?string $url = null;

    /**
     * @ApiProperty()
     * @Groups({"subdef:read", "asset:read", "publication:read"})
     */
    private ?string $downloadUrl = null;

    /**
     * @ApiProperty()
     * @Groups({"subdef:read", "asset:read"})
     */
    private ?string $uploadURL = null;

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

    public function getDownloadUrl(): ?string
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

    public function getUploadURL(): ?string
    {
        return $this->uploadURL;
    }

    public function setUploadURL(?string $uploadURL): void
    {
        $this->uploadURL = $uploadURL;
    }
}
