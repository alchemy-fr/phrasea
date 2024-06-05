<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Output\IntegrationDataOutput;
use App\Api\Provider\IntegrationDataProvider;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Arthem\ObjectReferenceBundle\Mapping\Attribute\ObjectReference;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'integration-data',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
    ],
    normalizationContext: [
        'groups' => [IntegrationData::GROUP_LIST],
    ],
    output: IntegrationDataOutput::class
)]
#[ApiResource(
    uriTemplate: '/integrations/{integrationId}/data',
    shortName: 'integration-data',
    operations: [
        new GetCollection(
            provider: IntegrationDataProvider::class,
        ),
    ],
    uriVariables: [
        'integrationId' => new Link(
            toProperty: 'integration',
            fromClass: WorkspaceIntegration::class
        ),
    ],
    normalizationContext: [
        'groups' => [IntegrationData::GROUP_LIST],
    ],
)]
#[ORM\Entity]
#[ORM\Index(columns: ['integration_id', 'object_type', 'object_id'], name: 'int_obj_idx')]
#[ORM\Index(columns: ['integration_id', 'name'], name: 'int_nam_idx')]
#[ApiFilter(SearchFilter::class, properties: [
    'integration' => 'exact',
    'objectType' => 'exact',
    'objectId' => 'exact',
])]
class IntegrationData extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    final public const GROUP_READ = 'int-data:read';
    final public const GROUP_LIST = 'int-data:index';
    final public const GROUP_WRITE = 'int-data:w';

    #[ORM\ManyToOne(targetEntity: WorkspaceIntegration::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?WorkspaceIntegration $integration = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
    private ?string $keyId = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
    private ?string $userId = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
    private $value;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    #[ObjectReference(keyLength: 15)]
    private \Closure|AbstractUuidEntity|null $object = null;
    private ?string $objectType;
    private ?string $objectId;

    public function getIntegration(): ?WorkspaceIntegration
    {
        return $this->integration;
    }

    public function setIntegration(?WorkspaceIntegration $integration): void
    {
        $this->integration = $integration;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getKeyId(): ?string
    {
        return $this->keyId;
    }

    public function setKeyId(?string $keyId): void
    {
        $this->keyId = $keyId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getObject(): ?AbstractUuidEntity
    {
        if ($this->object instanceof \Closure) {
            $this->object = $this->object->call($this);
        }

        return $this->object;
    }

    public function setObject(?AbstractUuidEntity $object): void
    {
        $this->object = $object;
    }

    public function getObjectType(): ?string
    {
        return $this->objectType;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }
}
