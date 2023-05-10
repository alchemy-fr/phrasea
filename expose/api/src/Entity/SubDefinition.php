<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
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
 *         "get"={},
 *         "delete"={
 *              "security"="is_granted('DELETE', object)"
 *         },
 *     },
 *  )
 */
class SubDefinition implements MediaInterface
{
    const THUMBNAIL = 'thumbnail';
    const PREVIEW = 'preview';
    const POSTER = 'poster';

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
     * @Groups({"subdef:read", "publication:read", "asset:read"})
     * @ORM\Column(type="bigint", options={"unsigned"=true})
     */
    private ?string $size = null;

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
        return (int) $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = (string) $size;
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
