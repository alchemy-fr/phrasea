<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\CommitAckAction;
use App\Controller\CommitAction;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Entity\AssetRepository")
 * @ORM\Table(name="asset_commit")
 * @ApiResource(
 *     shortName="commit",
 *     collectionOperations={
 *         "post"={
 *             "path"="/commit",
 *             "controller"=CommitAction::class,
 *         }
 *     },
 *     itemOperations={
 *         "ack"={
 *             "method"="POST",
 *             "path"="/commits/{id}/ack",
 *             "controller"=CommitAckAction::class,
 *         }
 *     },
 * )
 */
class Commit
{
    /**
     * @ApiProperty(identifier=true)
     * @Groups("asset_read")
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    private $id;

    /**
     * @var Asset[]|Collection
     * @ORM\OneToMany(targetEntity="App\Entity\Asset", mappedBy="commit")
     */
    private $assets;

    /**
     * @var array
     * @ORM\Column(type="json_array")
     */
    private $formData = [];

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $userId;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $token;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    private $acknowledged = false;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @ApiProperty()
     * @Groups("asset_read")
     */
    private $createdAt;

    /**
     * Not mapped.
     *
     * @var array
     */
    private $files = [];

    public function __construct()
    {
        $this->assets = new ArrayCollection();
        $this->createdAt = new DateTime();
        $this->id = Uuid::uuid4();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function setFiles(array $files): void
    {
        $this->files = $files;
    }

    public function getFormData(): array
    {
        return $this->formData;
    }

    public function setFormData(array $formData): void
    {
        $this->formData = $formData;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return Asset[]
     */
    public function getAssets(): Collection
    {
        return $this->assets;
    }

    public function assetCount(): int
    {
        return $this->assets->count();
    }

    public function generateToken(): void
    {
        $this->token = bin2hex(random_bytes(21));
    }

    public function toArray(): array
    {
        $data = [
            'files' => $this->files,
            'form' => $this->formData,
            'user_id' => $this->userId,
        ];

        if ($this->token) {
            $data['token'] = $this->token;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        if (isset($data['files'])) {
            $instance->setFiles($data['files']);
        }
        $instance->setFormData($data['form'] ?? []);
        $instance->setUserId($data['user_id']);

        return $instance;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged;
    }

    public function setAcknowledged(bool $acknowledged): void
    {
        $this->acknowledged = $acknowledged;
    }
}
