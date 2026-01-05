<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\ScopeVoter;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Consumer\Handler\CommitMessage;
use App\Controller\CommitAckAction;
use App\Controller\CommitAction;
use App\Security\ScopeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'commit',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Post(
            uriTemplate: '/commits/{id}/ack',
            controller: CommitAckAction::class,
            deserialize: false,
            name: 'ack',
        ),
        new Post(
            uriTemplate: '/commit',
            controller: CommitAction::class
        ),
        new GetCollection(
            security: 'is_granted("'.ScopeVoter::PREFIX.ScopeInterface::SCOPE_COMMIT_LIST.'") or is_granted("'.JwtUser::ROLE_ADMIN.'")'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]],
    order: [
        'acknowledged' => 'ASC',
        'createdAt' => 'ASC',
    ],
)]
#[ORM\Table(name: 'asset_commit')]
#[ORM\Entity(repositoryClass: CommitRepository::class)]
class Commit extends AbstractUuidEntity
{
    public const string GROUP_READ = 'commit:r';
    public const string GROUP_WRITE = 'commit:w';
    /**
     * @var Asset[]|Collection
     */
    #[ORM\OneToMany(mappedBy: 'commit', targetEntity: Asset::class, cascade: ['remove'])]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?Collection $assets = null;

    #[ORM\ManyToOne(targetEntity: Target::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['asset:read', self::GROUP_READ, self::GROUP_WRITE])]
    #[ApiFilter(filterClass: SearchFilter::class, strategy: 'exact', properties: ['target'])]
    private ?Target $target = null;

    #[ApiProperty(writable: false)]
    #[Groups(['asset:read', self::GROUP_READ])]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $totalSize = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['asset:read', self::GROUP_READ, self::GROUP_WRITE])]
    private array $formData = [];

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    #[Groups(['asset:read', self::GROUP_READ])]
    private ?string $formLocale = null;

    #[Groups([self::GROUP_WRITE])]
    public ?string $schemaId = null;

    #[Groups(['asset:read', self::GROUP_READ, self::GROUP_WRITE])]
    #[ORM\Column(type: Types::JSON)]
    private array $options = [];

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['asset:read', self::GROUP_READ, self::GROUP_WRITE])]
    private ?string $userId = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([self::GROUP_READ])]
    private ?string $token = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['asset:read', self::GROUP_READ])]
    #[ApiFilter(filterClass: BooleanFilter::class)]
    private bool $acknowledged = false;

    /**
     * If set, this email will be notified when asset consumer acknowledges the commit.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private bool $notify = false;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    #[Groups(['asset:read', self::GROUP_READ, self::GROUP_WRITE])]
    private ?string $locale = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['asset:read', self::GROUP_READ])]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ApiProperty]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['asset:read', self::GROUP_READ])]
    private readonly \DateTimeImmutable $createdAt;

    /**
     * Not mapped.
     */
    #[Groups([self::GROUP_WRITE])]
    private array $files = [];

    public function __construct()
    {
        parent::__construct();
        $this->assets = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Groups(['asset:read', self::GROUP_READ])]
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

    public function generateToken(): void
    {
        $this->token = bin2hex(random_bytes(21));
    }

    public function toMessage(): CommitMessage
    {
        return new CommitMessage(
            $this->target->getId(),
            $this->userId,
            $this->files,
            $this->formData,
            $this->notify,
            $this->locale,
            $this->schemaId,
            $this->options,
        );
    }

    public static function fromMessage(CommitMessage $message, EntityManagerInterface $em): self
    {
        $instance = new self();
        $instance->setFiles($message->getFiles());
        /** @var Target $target */
        $target = $em->getReference(Target::class, $message->getTargetId());
        $instance->setTarget($target);
        $instance->setFormData($message->getForm());
        $instance->setUserId($message->getUserId());
        $instance->setNotify($message->isNotify());
        $instance->setLocale($message->getLocale());
        $instance->schemaId = $message->getSchemaId();
        $instance->setOptions($message->getOptions());

        return $instance;
    }

    public function getCreatedAt(): \DateTimeImmutable
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
            $this->acknowledgedAt = new \DateTimeImmutable();
        }
        $this->acknowledged = $acknowledged;
    }

    public function isNotify(): bool
    {
        return $this->notify;
    }

    public function setNotify(bool $notify): void
    {
        $this->notify = $notify;
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

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function getFormLocale(): ?string
    {
        return $this->formLocale;
    }

    public function setFormLocale(?string $formLocale): void
    {
        $this->formLocale = $formLocale;
    }
}
