<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Output\IntegrationDataOutput;
use App\Entity\AbstractUuidEntity;
use App\Entity\Core\File;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'integration-data',
    operations: [
        new Get(),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
    ],
    normalizationContext: [
        'groups' => [IntegrationData::GROUP_LIST],
    ],
    output: IntegrationDataOutput::class
)]
#[ORM\Table]
#[ORM\Index(columns: ['integration_id', 'file_id', 'name'], name: 'name')]
#[ORM\Entity]
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

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?File $file = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Groups([IntegrationData::GROUP_LIST])]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups([IntegrationData::GROUP_LIST])]
    private ?string $keyId = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Groups([IntegrationData::GROUP_LIST])]
    private $value;

    public function getIntegration(): ?WorkspaceIntegration
    {
        return $this->integration;
    }

    public function setIntegration(?WorkspaceIntegration $integration): void
    {
        $this->integration = $integration;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): void
    {
        $this->file = $file;
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
}
