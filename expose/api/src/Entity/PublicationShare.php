<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Api\Processor\RevokePublicationShareProcessor;
use App\Api\Provider\PublicationShareResolveProvider;
use App\Api\Provider\PublicationSharesProvider;
use App\Repository\PublicationShareRepository;
use App\Security\Voter\PublicationVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\String\ByteString;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A link opening a publication to whoever holds its token, whatever the visibility
 * and the security method of the publication (not its sub-publications).
 */
#[ApiResource(
    shortName: 'publication-share',
    operations: [
        new GetCollection(
            uriTemplate: '/publications/{id}/shares',
            uriVariables: [
                'id' => new Link(toProperty: 'publication', fromClass: Publication::class),
            ],
            paginationEnabled: false,
            provider: PublicationSharesProvider::class,
        ),
        new Post(
            uriTemplate: '/publications/{id}/shares',
            uriVariables: [
                'id' => new Link(toProperty: 'publication', fromClass: Publication::class),
            ],
            // The provider resolves the publication, checks the right and returns the share to populate
            read: true,
            provider: PublicationSharesProvider::class,
        ),
        new Get(
            uriTemplate: '/publication-shares/{id}',
            security: 'is_granted("'.PublicationVoter::OPERATOR.'", object.getPublication())',
        ),
        new Patch(
            uriTemplate: '/publication-shares/{id}',
            security: 'is_granted("'.PublicationVoter::OPERATOR.'", object.getPublication())',
        ),
        new Delete(
            uriTemplate: '/publication-shares/{id}',
            security: 'is_granted("'.PublicationVoter::OPERATOR.'", object.getPublication())',
        ),
        new Post(
            uriTemplate: '/publication-shares/{id}/revoke',
            status: 200,
            openapi: new OpenApiOperation(
                summary: 'Revoke a share link',
                description: 'Revoke a share link, for good. Revoking it again changes nothing.',
            ),
            security: 'is_granted("'.PublicationVoter::OPERATOR.'", object.getPublication())',
            throwOnNotFound: true,
            deserialize: false,
            validate: false,
            processor: RevokePublicationShareProcessor::class,
        ),
        new Get(
            uriTemplate: '/publication-shares/resolve/{token}',
            uriVariables: [
                'token' => new Link(fromClass: self::class, identifiers: ['token']),
            ],
            openapi: new OpenApiOperation(
                summary: 'Resolve a share link',
                description: 'The publication opened by an active share link; 404 for an unknown, expired or revoked one.',
            ),
            normalizationContext: [
                'groups' => [self::GROUP_PUBLIC],
            ],
            provider: PublicationShareResolveProvider::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
    denormalizationContext: [
        'groups' => [self::GROUP_WRITE],
    ],
)]
#[ORM\Entity(repositoryClass: PublicationShareRepository::class)]
class PublicationShare
{
    private const string GROUP_PREFIX = 'publication_share:';
    final public const string GROUP_READ = self::GROUP_PREFIX.'r';
    final public const string GROUP_WRITE = self::GROUP_PREFIX.'w';
    final public const string GROUP_PUBLIC = self::GROUP_PREFIX.'public';

    final public const string STATUS_ACTIVE = 'active';
    final public const string STATUS_EXPIRED = 'expired';
    final public const string STATUS_REVOKED = 'revoked';

    final public const int TOKEN_LENGTH = 64;

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_READ])]
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Publication::class, inversedBy: 'shares')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups([self::GROUP_PUBLIC])]
    private ?Publication $publication = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: self::TOKEN_LENGTH, unique: true)]
    #[Groups([self::GROUP_READ])]
    private string $token;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE, self::GROUP_PUBLIC])]
    private ?\DateTimeImmutable $expiresAt = null;

    /**
     * Set when the expiration is given a new value, which must then be in the future.
     */
    private bool $expiresAtChanged = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups([self::GROUP_READ])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?string $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?string $revokedBy = null;

    /**
     * The permalink of the share, in the expose client.
     */
    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private ?string $url = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
        $this->token = ByteString::fromRandom(self::TOKEN_LENGTH)->toString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(Publication $publication): void
    {
        $this->publication = $publication;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void
    {
        if ($expiresAt?->getTimestamp() !== $this->expiresAt?->getTimestamp()) {
            $this->expiresAtChanged = true;
        }

        $this->expiresAt = $expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getRevokedBy(): ?string
    {
        return $this->revokedBy;
    }

    public function revoke(?string $userId): void
    {
        if ($this->isRevoked()) {
            return;
        }

        $this->revokedAt = new \DateTimeImmutable();
        $this->revokedBy = $userId;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }

    public function isActive(?\DateTimeImmutable $now = null): bool
    {
        return !$this->isRevoked() && !$this->isExpired($now);
    }

    #[Groups([self::GROUP_READ])]
    public function getStatus(): string
    {
        return match (true) {
            $this->isRevoked() => self::STATUS_REVOKED,
            $this->isExpired() => self::STATUS_EXPIRED,
            default => self::STATUS_ACTIVE,
        };
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    /**
     * Callback constraint, see config/validator/validation.yaml.
     */
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->isRevoked()) {
            $context
                ->buildViolation('A revoked share cannot be modified.')
                ->addViolation();

            return;
        }

        if ($this->expiresAtChanged && $this->isExpired()) {
            $context
                ->buildViolation('The expiration date must be in the future.')
                ->atPath('expiresAt')
                ->addViolation();
        }
    }
}
