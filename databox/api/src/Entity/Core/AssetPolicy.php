<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\AssetFileVersionCollectionProvider;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Ramsey\Uuid\UuidInterface;

#[ApiResource(
    shortName: 'asset-file-version',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new GetCollection(),
    ],
    normalizationContext: [
        'groups' => [
            AssetPolicy::GROUP_LIST,
        ],
    ],
    provider: AssetFileVersionCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\Entity]
class AssetPolicy extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use WorkspaceTrait;
    final public const string GROUP_READ = 'ap:read';
    final public const string GROUP_LIST = 'ap:index';

    final public const int TYPE_USER = 0;
    final public const int TYPE_GROUP = 1;

    #[Column(type: Types::SMALLINT)]
    protected ?int $userType = null; // TODO Make multiple

    #[Column(type: Types::STRING, length: 36, nullable: true)]
    protected ?string $userId = null; // TODO Make multiple

    #[Column(type: Types::INTEGER, nullable: false)]
    private int $priority = 0;

    #[Column(type: Types::JSON, nullable: false)]
    private array $conditions = [];

    #[Column(type: Types::JSON, nullable: false)]
    private array $actions = [];

    #[ORM\OneToMany(mappedBy: 'policy', targetEntity: AssetPolicyDependency::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Collection $dependencies = null;

    public function __construct(UuidInterface|string|null $id = null)
    {
        parent::__construct($id);
        $this->dependencies = new ArrayCollection();
    }
}
