<?php

namespace App\Entity\Page;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Provider\PageBySlugProvider;
use App\Entity\Traits\OwnerIdTrait;
use App\Listener\OwnerPersistableInterface;
use App\Repository\Page\PageRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'page',
    operations: [
        new GetCollection(
            normalizationContext: [
                'groups' => [self::GROUP_LIST],
            ]),
        new Get(),
        new Get(
            uriTemplate: '/page-by-slug/{slug}',
            uriVariables: [
                'slug' => 'slug',
            ],
            name: 'get_page_by_slug',
            provider: PageBySlugProvider::class
        ),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
        new Put(
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
        ),
        new Post(
            securityPostValidation: 'is_granted("'.AbstractVoter::CREATE.'", object)'
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST, self::GROUP_READ],
    ],
)]
#[ORM\Entity(repositoryClass: PageRepository::class)]
#[UniqueEntity(fields: ['slug'])]
class Page extends AbstractUuidEntity implements OwnerPersistableInterface, AclObjectInterface
{
    use OwnerIdTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    final public const int OBJECT_INDEX = 8;
    final public const string OBJECT_TYPE = 'page';

    final public const string GROUP_PREFIX = 'page:';
    final public const string GROUP_READ = self::GROUP_PREFIX.'r';
    final public const string GROUP_LIST = self::GROUP_PREFIX.'i';
    final public const string GROUP_WRITE = self::GROUP_PREFIX.'w';

    #[ORM\Column(type: Types::STRING, length: 36)]
    // Skip validation
    protected ?string $ownerId = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    #[Assert\Length(max: 100)]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?string $slug = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    private ?string $title = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private bool $public = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private bool $enabled = false;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private array $data = [];

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $options = [];

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId();
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? 'Page - '.$this->getId();
    }

    public function isHomepage(): bool
    {
        return null === $this->slug;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug ?: null;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
