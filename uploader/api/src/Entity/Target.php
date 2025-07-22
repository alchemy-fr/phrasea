<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\DataProvider\TargetDataProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'target',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("'.JwtUser::ROLE_ADMIN.'")'),
        new Put(security: 'is_granted("'.JwtUser::ROLE_ADMIN.'")'),
        new Post(security: 'is_granted("'.JwtUser::ROLE_ADMIN.'")'),
        new GetCollection(
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            provider: TargetDataProvider::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['target:index'],
    ],
    denormalizationContext: [
        'groups' => ['target:write'],
    ]
)]
#[ORM\Table]
#[ORM\Entity]
class Target extends AbstractUuidEntity implements \Stringable
{
    #[Groups(['target:index'])]
    #[Assert\Regex('/^[a-z][a-z0-9_-]+/')]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true, nullable: true)]
    protected ?string $slug = null;

    #[ORM\Column(type: Types::STRING, length: 1000)]
    #[Assert\Length(max: 1000)]
    #[Assert\NotBlank]
    #[Groups(['target:index'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    #[Groups(['target:read'])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $hidden = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['target:index'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Url]
    #[Groups(['target:write'])]
    private ?string $targetUrl = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['target:write'])]
    private ?string $defaultDestination = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['target:write'])]
    private ?string $authorizationScheme = null;

    #[ORM\Column(type: Types::STRING, length: 2000, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['target:write'])]
    private ?string $authorizationKey = null;

    /**
     * Null value allows everyone.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['target:write'])]
    private ?array $allowedGroups = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['target:index'])]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(mappedBy: 'target', targetEntity: TargetParams::class)]
    private ?TargetParams $targetParams = null;

    /**
     * Used for sub resource mapping.
     */
    private ?FormSchema $formSchema = null;

    public function __construct()
    {
        parent::__construct();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Groups(['target:index'])]
    public function getId(): string
    {
        return parent::getId();
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getAuthorizationKey(): ?string
    {
        return $this->authorizationKey;
    }

    public function setAuthorizationKey(?string $authorizationKey): void
    {
        $this->authorizationKey = $authorizationKey;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): void
    {
        $this->targetUrl = $targetUrl;
    }

    public function getAllowedGroups(): ?array
    {
        return $this->allowedGroups;
    }

    public function setAllowedGroups(?array $allowedGroups): void
    {
        $this->allowedGroups = $allowedGroups;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDefaultDestination(): ?string
    {
        return $this->defaultDestination;
    }

    public function getPullModeUrl(): string
    {
        return sprintf('%s/commits?target=%s', getenv('UPLOADER_API_URL'), $this->getId());
    }

    public function setDefaultDestination(?string $defaultDestination): void
    {
        $this->defaultDestination = $defaultDestination;
    }

    public function __toString(): string
    {
        return (string) ($this->getName() ?? $this->getId());
    }

    public function getAuthorizationScheme(): ?string
    {
        return $this->authorizationScheme;
    }

    public function setAuthorizationScheme(?string $authorizationScheme): void
    {
        $this->authorizationScheme = $authorizationScheme;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }
}
