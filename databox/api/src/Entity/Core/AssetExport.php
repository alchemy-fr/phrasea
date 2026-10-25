<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Model\Output\UserOutput;
use App\Api\Processor\AssetExportDelegationProcessor;
use App\Api\Processor\AssetExportProcessor;
use App\Entity\Traits\OwnerIdTrait;
use App\Model\ExportStatusEnum;
use App\Model\UserData;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'asset-export',
    operations: [
        new Get(
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
        ),
        new Post(
            processor: AssetExportProcessor::class,
        ),
        new Patch(
            uriTemplate: '/asset-exports/{id}/delegation',
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            denormalizationContext: ['groups' => [self::GROUP_DELEGATION]],
            processor: AssetExportDelegationProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ, self::GROUP_ALL],
    ],
    denormalizationContext: [
        'groups' => [self::GROUP_WRITE, self::GROUP_ALL],
    ],
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
)]
#[ORM\Entity]
class AssetExport extends AbstractUuidEntity
{
    use OwnerIdTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    private const string GROUP_PREFIX = 'asset-export:';
    final public const string GROUP_READ = self::GROUP_PREFIX.'r';
    final public const string GROUP_WRITE = self::GROUP_PREFIX.'w';
    final public const string GROUP_ALL = self::GROUP_PREFIX.'a';
    final public const string GROUP_DELEGATION = self::GROUP_PREFIX.'delegation';

    #[Groups(self::GROUP_READ)]
    #[ORM\Column(type: Types::SMALLINT, nullable: false, enumType: ExportStatusEnum::class)]
    private ExportStatusEnum $status = ExportStatusEnum::Pending;

    #[ORM\Column(type: Types::STRING, length: 36)]
    #[Assert\Length(min: 1, max: 36)]
    protected ?string $ownerId = null;

    #[Groups([self::GROUP_WRITE, self::GROUP_DELEGATION])]
    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    #[Assert\Uuid]
    private ?string $delegateUserId = null;

    // The capability itself is never persisted or returned by the API.
    #[Groups([self::GROUP_WRITE, self::GROUP_DELEGATION])]
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Assert\Regex(pattern: '/^[a-f0-9]{64}$/')]
    private ?string $delegationHash = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private ?array $userData = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $path = null;

    /**
     * @var string[]
     */
    #[Groups(self::GROUP_WRITE)]
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[ORM\Column(type: Types::JSON, nullable: false)]
    private ?array $assets = null;

    /**
     * @var string[]
     */
    #[Groups(self::GROUP_WRITE)]
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[ORM\Column(type: Types::JSON, nullable: false)]
    private ?array $renditions = null;

    #[Groups(self::GROUP_READ)]
    public ?string $downloadUrl = null;

    #[Groups([self::GROUP_READ])]
    public ?UserOutput $owner = null;

    public function getStatus(): ExportStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ExportStatusEnum $status): void
    {
        $this->status = $status;
    }

    public function getAssets(): ?array
    {
        return $this->assets;
    }

    public function setAssets(?array $assets): void
    {
        $this->assets = $assets;
    }

    public function getRenditions(): ?array
    {
        return $this->renditions;
    }

    public function setRenditions(?array $renditions): void
    {
        $this->renditions = $renditions;
    }

    public function getUserData(): UserData
    {
        return UserData::fromArray($this->userData);
    }

    public function setUserData(UserData $userData): void
    {
        $this->userData = $userData->toArray();
    }

    public function getDelegateUserId(): ?string
    {
        return $this->delegateUserId;
    }

    public function setDelegateUserId(?string $delegateUserId): void
    {
        $this->delegateUserId = $delegateUserId;
    }

    public function getDelegationHash(): ?string
    {
        return $this->delegationHash;
    }

    public function setDelegationHash(?string $delegationHash): void
    {
        $this->delegationHash = $delegationHash;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }
}
