<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Util\RandomUtil;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Filter\ExactSearchFilter;
use App\Api\Processor\CreateUploadLinkProcessor;
use App\Api\Provider\CurrentUploadLinkProvider;
use App\Repository\UploadLinkRepository;
use App\Security\UploadLinkUser;
use App\Security\Voter\UploadLinkVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A secret URL letting someone without an account upload to one target, within limits counted over
 * every commit made with it.
 */
#[ApiResource(
    shortName: 'UploadLink',
    operations: [
        // Before the item routes: "current" is not an id
        new Get(
            uriTemplate: '/upload-links/current',
            security: 'is_granted("'.UploadLinkUser::ROLE.'")',
            name: self::ROUTE_CURRENT,
            provider: CurrentUploadLinkProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/upload-links',
            security: 'is_granted("'.UploadLinkVoter::CREATE.'")',
            parameters: [
                'target' => new QueryParameter(
                    filter: ExactSearchFilter::class,
                    property: 'target',
                ),
            ],
        ),
        new Get(
            uriTemplate: '/upload-links/{id}',
            requirements: ['id' => self::UUID_PATTERN],
            security: 'is_granted("'.UploadLinkVoter::READ.'", object)',
        ),
        new Post(
            uriTemplate: '/upload-links',
            security: 'is_granted("'.UploadLinkVoter::CREATE.'")',
            denormalizationContext: ['groups' => [self::GROUP_WRITE, self::GROUP_CREATE]],
            processor: CreateUploadLinkProcessor::class,
        ),
        new Patch(
            uriTemplate: '/upload-links/{id}',
            requirements: ['id' => self::UUID_PATTERN],
            security: 'is_granted("'.UploadLinkVoter::EDIT.'", object)',
        ),
        new Delete(
            uriTemplate: '/upload-links/{id}',
            requirements: ['id' => self::UUID_PATTERN],
            security: 'is_granted("'.UploadLinkVoter::DELETE.'", object)',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]],
    order: ['createdAt' => 'DESC'],
)]
#[ORM\Table(name: 'upload_link')]
#[ORM\Entity(repositoryClass: UploadLinkRepository::class)]
class UploadLink extends AbstractUuidEntity
{
    final public const string GROUP_READ = 'upload_link:r';
    final public const string GROUP_WRITE = 'upload_link:w';
    final public const string GROUP_CREATE = 'upload_link:c';

    final public const string ROUTE_CURRENT = 'upload_link_current';

    final public const string STATUS_ACTIVE = 'active';
    final public const string STATUS_DISABLED = 'disabled';
    final public const string STATUS_EXPIRED = 'expired';
    final public const string STATUS_EXHAUSTED = 'exhausted';

    private const string UUID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    #[ORM\ManyToOne(targetEntity: Target::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups([self::GROUP_READ, self::GROUP_CREATE])]
    private ?Target $target = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?string $name = null;

    /**
     * Stored as is, so that its creator can copy the link again.
     */
    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private string $token;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?int $maxAssets = null;

    /**
     * In bytes.
     */
    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    #[Assert\Positive]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?int $maxTotalSize = null;

    /**
     * Reserved when a file is uploaded, released when a file not committed yet is removed.
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private int $usedAssets = 0;

    /**
     * An int, as DBAL hydrates a BIGINT: a string would look changed to the UnitOfWork, which would write
     * the counters back with their former value on the next flush, over the reservations made in SQL.
     */
    #[ORM\Column(type: Types::BIGINT)]
    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private int $usedSize = 0;

    /**
     * The user who created the link: the commits made with it are theirs.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private ?string $ownerId = null;

    /**
     * What was entered in the form of the last commit, without the target data: offered again for the next one.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $lastFormData = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeImmutable $lastCommitAt = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        parent::__construct();
        $this->token = self::generateToken();
    }

    public static function generateToken(): string
    {
        return RandomUtil::generateString(64);
    }

    #[Groups([self::GROUP_READ])]
    public function getId(): string
    {
        return parent::getId();
    }

    public function getTarget(): ?Target
    {
        return $this->target;
    }

    public function setTarget(?Target $target): void
    {
        $this->target = $target;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = null !== $name && '' !== trim($name) ? $name : null;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getMaxAssets(): ?int
    {
        return $this->maxAssets;
    }

    public function setMaxAssets(?int $maxAssets): void
    {
        $this->maxAssets = $maxAssets;
    }

    public function getMaxTotalSize(): ?int
    {
        return $this->maxTotalSize;
    }

    public function setMaxTotalSize(?int $maxTotalSize): void
    {
        $this->maxTotalSize = $maxTotalSize;
    }

    public function getUsedAssets(): int
    {
        return $this->usedAssets;
    }

    public function getUsedSize(): int
    {
        return $this->usedSize;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getLastFormData(): ?array
    {
        return $this->lastFormData;
    }

    public function setLastFormData(?array $lastFormData): void
    {
        $this->lastFormData = $lastFormData;
    }

    public function getLastCommitAt(): ?\DateTimeImmutable
    {
        return $this->lastCommitAt;
    }

    public function setLastCommitAt(?\DateTimeImmutable $lastCommitAt): void
    {
        $this->lastCommitAt = $lastCommitAt;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= $now;
    }

    public function isExhausted(): bool
    {
        return (null !== $this->maxAssets && $this->usedAssets >= $this->maxAssets)
            || (null !== $this->maxTotalSize && $this->getUsedSize() >= $this->getMaxTotalSize());
    }

    /**
     * Whether it still authenticates: an exhausted link does, so that its user sees why nothing more can be sent.
     */
    public function isUsable(\DateTimeImmutable $now): bool
    {
        return $this->enabled && !$this->isExpired($now);
    }

    #[Groups([self::GROUP_READ])]
    public function getStatus(): string
    {
        $now = new \DateTimeImmutable();

        return match (true) {
            !$this->enabled => self::STATUS_DISABLED,
            $this->isExpired($now) => self::STATUS_EXPIRED,
            $this->isExhausted() => self::STATUS_EXHAUSTED,
            default => self::STATUS_ACTIVE,
        };
    }
}
