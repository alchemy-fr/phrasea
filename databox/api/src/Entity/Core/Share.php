<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Output\ShareAlternateUrlOutput;
use App\Api\Processor\ShareProcessor;
use App\Api\Provider\ShareCollectionProvider;
use App\Api\Provider\ShareReadProvider;
use App\Api\Provider\ShareRenditionProvider;
use App\Entity\Traits\OwnerIdTrait;
use App\Listener\OwnerPersistableInterface;
use App\Repository\Core\ShareRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\String\ByteString;

#[ApiResource(
    shortName: 'share',
    operations: [
        new Get(
            uriTemplate: '/shares/{id}/public',
            normalizationContext: [
                'groups' => [
                    self::GROUP_PUBLIC_READ,
                ],
            ],
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
            name: 'share_public',
        ),
        new Get(
            uriTemplate: '/s/{id}/r/{rendition}',
            uriVariables: [
                'id' => 'id',
                'rendition' => 'rendition',
            ],
            normalizationContext: [
                'groups' => [
                    self::GROUP_PUBLIC_READ,
                ],
            ],
            name: 'share_public_rendition',
            provider: ShareRenditionProvider::class,
            extraProperties: [
                '_api_disable_swagger_provider' => true,
            ],
        ),
        new Get(
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
            provider: ShareReadProvider::class,
        ),
        new Put(
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            provider: ShareReadProvider::class,
        ),
        new Delete(
            security: 'is_granted("'.AbstractVoter::DELETE.'", object)'
        ),
        new GetCollection(
            provider: ShareCollectionProvider::class,
        ),
        new Post(
            securityPostDenormalize: 'is_granted("'.AbstractVoter::CREATE.'", object)',
            validate: false,
            provider: ShareReadProvider::class,
            processor: ShareProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
)]
#[ORM\Entity(repositoryClass: ShareRepository::class)]
#[ApiFilter(filterClass: SearchFilter::class, strategy: 'exact', properties: ['asset'])]
class Share extends AbstractUuidEntity implements OwnerPersistableInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use OwnerIdTrait;
    final public const string GROUP_READ = 'share:read';
    final public const string GROUP_PUBLIC_READ = 'share:public';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?string $title = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    #[Groups([self::GROUP_READ])]
    private bool $enabled = true;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups([self::GROUP_PUBLIC_READ, self::GROUP_READ])]
    private ?Asset $asset = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ])]
    protected ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ])]
    protected ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: false)]
    #[Groups([self::GROUP_READ])]
    private ?string $token = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $config = [];

    /**
     * @var ShareAlternateUrlOutput[]
     */
    #[Groups([self::GROUP_READ])]
    public array $alternateUrls = [];

    public function __construct(UuidInterface|string|null $id = null)
    {
        parent::__construct($id);
        $this->token = ByteString::fromRandom(64)->toString();
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
