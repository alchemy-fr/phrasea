<?php

declare(strict_types=1);

namespace App\Entity\SavedSearch;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\SavedSearchInput;
use App\Api\Model\Output\SavedSearchOutput;
use App\Api\Provider\SavedSearchCollectionProvider;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\WithOwnerIdInterface;
use App\Repository\SavedSearch\SavedSearchRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'saved-search',
    operations: [
        new GetCollection(security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")'),
        new Get(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ]
        ),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
        new Put(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
        ),
        new Post(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            securityPostValidation: 'is_granted("'.AbstractVoter::CREATE.'", object)'
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST],
    ],
    input: SavedSearchInput::class,
    output: SavedSearchOutput::class,
    provider: SavedSearchCollectionProvider::class,
)]
#[ORM\Entity(repositoryClass: SavedSearchRepository::class)]
class SavedSearch extends AbstractUuidEntity implements WithOwnerIdInterface, AclObjectInterface
{
    use OwnerIdTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;
    final public const string OBJECT_TYPE = 'saved_search';
    final public const string GROUP_READ = 'ssearch:read';
    final public const string GROUP_LIST = 'ssearch:index';
    final public const string GROUP_WRITE = 'ssearch:w';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $public = false;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $data = [];

    public function __construct(UuidInterface|string|null $id = null)
    {
        parent::__construct($id);
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId();
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? 'SavedSearch - '.$this->getId();
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }
}
