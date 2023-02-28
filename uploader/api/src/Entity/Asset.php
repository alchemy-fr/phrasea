<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use App\Controller\AssetAckAction;
use App\Controller\DownloadAssetAction;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Entity\AssetRepository")
 * @ApiResource(
 *     normalizationContext={
 *         "groups"={"asset:read"},
 *     },
 *     denormalizationContext={
 *         "groups"={"asset:write"},
 *     },
 *     itemOperations={
 *         "get"={"access_control"="is_granted('READ_META', object)"},
 *         "download"={
 *             "access_control"="is_granted('DOWNLOAD', object)",
 *             "method"="GET",
 *             "path"="/assets/{id}/download",
 *             "controller"=DownloadAssetAction::class,
 *         },
 *         "ack"={
 *             "method"="POST",
 *             "path"="/assets/{id}/ack",
 *             "controller"=AssetAckAction::class,
 *              "defaults"={
 *                  "_api_receive"=false,
 *                  "_api_respond"=true,
 *             },
 *         }
 *     },
 * )
 */
class Asset
{
    /**
     * @ApiProperty(identifier=true)
     * @Groups("asset:read")
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $path;

    /**
     * @ORM\Column(type="json", nullable=true)
     * @Groups("asset:read", "asset:write")
     */
    private ?array $data = [];

    /**
     * Dynamic signed URL.
     *
     * @ApiProperty()
     * @Groups({"asset:read"})
     */
    private ?string $url = null;

    /**
     * @var int|string
     * @Groups("asset:read")
     * @ORM\Column(type="bigint", options={"unsigned"=true})
     */
    private $size;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups("asset:read")
     */
    private $originalName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @ApiProperty()
     * @Groups("asset:read")
     */
    private $mimeType;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Target")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Target $target = null;

    /**
     * @var Commit|null
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Commit", inversedBy="assets")
     */
    private $commit;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     * @Groups("asset:read")
     * @ApiFilter(BooleanFilter::class)
     */
    private $acknowledged = false;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @ApiProperty()
     * @Groups("asset:read")
     */
    private $createdAt;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $userId;

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

    public function setSize($size): void
    {
        $this->size = (string) $size;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): void
    {
        $this->originalName = $originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function isCommitted(): bool
    {
        return null !== $this->commit;
    }

    /**
     * @Groups("asset:read")
     */
    public function getFormData(): ?array
    {
        return $this->commit ? $this->commit->getFormData() : null;
    }

    /**
     * @ApiProperty()
     */
    public function getToken(): ?string
    {
        return $this->commit ? $this->commit->getToken() : null;
    }

    public function getCommit(): ?Commit
    {
        return $this->commit;
    }

    public function setCommit(?Commit $commit): void
    {
        $this->commit = $commit;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged;
    }

    public function setAcknowledged(bool $acknowledged): void
    {
        $this->acknowledged = $acknowledged;
    }

    public function getTarget(): ?Target
    {
        return $this->target;
    }

    public function setTarget(?Target $target): void
    {
        $this->target = $target;
    }

    public function getData(): array
    {
        return null !== $this->data ? $this->data : [];
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
    }
}
