<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Controller\CommitAckAction;
use App\Controller\CommitAction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'commit', operations: [
    new Get(security: 'is_granted("READ", object)'),
    new Post(
        uriTemplate: '/commits/{id}/ack',
        defaults: ['_api_receive' => false, '_api_respond' => true],
        controller: CommitAckAction::class,
        name: 'ack',
    ),
    new Post(
        uriTemplate: '/commit',
        controller: CommitAction::class
    ),
    new GetCollection(
        security: 'is_granted("ROLE_COMMIT:LIST") or is_granted("'.RemoteUser::ROLE_ADMIN.'")'),
],
    normalizationContext: ['groups' => ['commit:read']],
    denormalizationContext: ['groups' => ['commit:write']],
    order: [
        'acknowledged' => 'ASC',
        'createdAt' => 'ASC',
    ],
)]
#[ORM\Table(name: 'asset_commit')]
#[ORM\Entity(repositoryClass: CommitRepository::class)]
class Commit extends AbstractUuidEntity
{
    /**
     * @var Asset[]|Collection
     */
    #[ORM\OneToMany(mappedBy: 'commit', targetEntity: Asset::class, cascade: ['remove'])]
    #[Groups(['commit:read', 'commit:write'])]
    private ?Collection $assets = null;

    #[ORM\ManyToOne(targetEntity: Target::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['asset:read', 'commit:read', 'commit:write'])]
    #[ApiFilter(filterClass: SearchFilter::class, strategy: 'exact', properties: ['target'])]
    private ?Target $target = null;

    #[ApiProperty(writable: false)]
    #[Groups(['asset:read', 'commit:read'])]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $totalSize = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['asset:read', 'commit:read'])]
    private array $formData = [];

    #[Groups(['asset:read', 'commit:read', 'commit:write'])]
    #[ORM\Column(type: 'json')]
    private array $options = [];

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['asset:read', 'commit:read', 'commit:write'])]
    private ?string $userId = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['commit:read'])]
    private ?string $token = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: 'boolean')]
    #[Groups(['asset:read', 'commit:read'])]
    #[ApiFilter(filterClass: BooleanFilter::class)]
    private bool $acknowledged = false;

    /**
     * If set, this email will be notified when asset consumer acknowledges the commit.
     */
    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['commit:read', 'commit:write'])]
    private ?string $notifyEmail = null;

    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    #[Groups(['asset:read', 'commit:read', 'commit:write'])]
    private ?string $locale = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['asset:read', 'commit:read'])]
    private ?\DateTime $acknowledgedAt = null;

    #[ApiProperty]
    #[ORM\Column(type: 'datetime')]
    #[Groups(['asset:read', 'commit:read'])]
    private readonly \DateTime $createdAt;

    /**
     * Not mapped.
     */
    #[Groups(['commit:write'])]
    private array $files = [];

    public function __construct()
    {
        parent::__construct();
        $this->assets = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    #[Groups(['asset:read', 'commit:read'])]
    public function getId(): string
    {
        return parent::getId();
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

    #[Groups(['__NONE__'])]
    public function getFormDataJson(): string
    {
        return \GuzzleHttp\json_encode($this->formData, JSON_PRETTY_PRINT);
    }

    public function setFormDataJson(?string $json): void
    {
        $json ??= '{}';
        $this->formData = \GuzzleHttp\json_decode($json, true);
    }

    #[Groups(['__NONE__'])]
    public function getOptionsJson(): string
    {
        return \GuzzleHttp\json_encode($this->options, JSON_PRETTY_PRINT);
    }

    public function setOptionsJson(?string $json): void
    {
        $json ??= '{}';

        $this->options = \GuzzleHttp\json_decode($json, true);
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
            'target_id' => $this->target->getId(),
            'notify_email' => $this->notifyEmail,
            'locale' => $this->locale,
            'options' => $this->options,
        ];

        if ($this->token) {
            $data['token'] = $this->token;
        }

        return $data;
    }

    public static function fromArray(array $data, EntityManagerInterface $em): self
    {
        $instance = new self();
        if (isset($data['files'])) {
            $instance->setFiles($data['files']);
        }
        /** @var Target $target */
        $target = $em->getReference(Target::class, $data['target_id']);
        $instance->setTarget($target);
        $instance->setFormData($data['form'] ?? []);
        $instance->setUserId($data['user_id']);
        $instance->setNotifyEmail($data['notify_email'] ?? null);
        $instance->setLocale($data['locale'] ?? null);
        $instance->setOptions($data['options'] ?? []);

        return $instance;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged;
    }

    public function setAcknowledged(bool $acknowledged): void
    {
        if ($acknowledged) {
            $this->acknowledgedAt = new \DateTime();
        }
        $this->acknowledged = $acknowledged;
    }

    public function getNotifyEmail(): ?string
    {
        return $this->notifyEmail;
    }

    public function setNotifyEmail(?string $notifyEmail): void
    {
        $this->notifyEmail = $notifyEmail;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getTotalSize(): int
    {
        return (int) $this->totalSize;
    }

    public function setTotalSize($totalSize): void
    {
        $this->totalSize = (string) $totalSize;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getTarget(): ?Target
    {
        return $this->target;
    }

    public function setTarget(?Target $target): void
    {
        $this->target = $target;
    }
}
