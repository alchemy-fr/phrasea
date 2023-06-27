<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use App\Controller\AssetAckAction;
use App\Controller\DownloadAssetAction;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
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
#[ORM\Entity(repositoryClass: AssetRepository::class)]
class Asset
{
    /**
     * @ApiProperty(identifier=true)
     *
     * @var Uuid
     */
    #[Groups('asset:read')]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    protected $id;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $path = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups('asset:read')]
    private ?array $data = [];

    /**
     * Dynamic signed URL.
     *
     * @ApiProperty()
     */
    #[Groups(['asset:read'])]
    private ?string $url = null;

    #[Groups('asset:read')]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $size = null;

    /**
     * @ApiProperty(iri="http://schema.org/name")
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('asset:read')]
    private ?string $originalName = null;

    /**
     * @ApiProperty()
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('asset:read')]
    private ?string $mimeType = null;

    #[ORM\ManyToOne(targetEntity: Target::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Target $target = null;

    #[ORM\ManyToOne(targetEntity: Commit::class, inversedBy: 'assets')]
    private ?Commit $commit = null;

    /**
     * @ApiFilter(BooleanFilter::class)
     */
    #[ORM\Column(type: 'boolean')]
    #[Groups('asset:read')]
    private bool $acknowledged = false;

    /**
     * @ApiProperty()
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups('asset:read')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $userId = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    #[Groups('asset:read')]
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

    public function getCreatedAt(): \DateTime
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
        return $this->data ?? [];
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
    }
}
