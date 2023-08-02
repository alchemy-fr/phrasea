<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Controller\GetTargetFormSchemaAction;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'form-schema',
    operations: [
        new Get(
            uriTemplate: '/targets/{id}/form-schema',
            controller: GetTargetFormSchemaAction::class,
            read: false,
            name: 'get_form_schema',
        ),
        new Get(security: 'is_granted("EDIT_FORM_SCHEMA")'),
        new Delete(security: 'is_granted("EDIT_FORM_SCHEMA")'),
        new Put(security: 'is_granted("EDIT_FORM_SCHEMA")'),
        new Post(security: 'is_granted("EDIT_FORM_SCHEMA")'),
        new GetCollection(security: 'is_granted("EDIT_FORM_SCHEMA")'),
    ],
    normalizationContext: [
        'groups' => ['formschema:index'],
    ],
    denormalizationContext: [
        'groups' => ['formschema:write'],
    ]
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_target_locale', columns: ['locale', 'target_id'])]
#[ORM\Entity(repositoryClass: FormSchemaRepository::class)]
class FormSchema extends AbstractUuidEntity implements AclObjectInterface
{
    #[ORM\ManyToOne(targetEntity: Target::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['formschema:index', 'formschema:write'])]
    private ?Target $target = null;

    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    #[Groups(['formschema:index', 'formschema:write'])]
    private ?string $locale = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['formschema:index', 'formschema:write'])]
    private array $data = [];

    #[ORM\Column(type: 'datetime')]
    #[Groups(['targetparams:index'])]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct(string $id = null)
    {
        parent::__construct($id);
    }

    #[Groups(['formschema:index'])]
    public function getId(): string
    {
        return parent::getId();
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getJsonData(): string
    {
        return \GuzzleHttp\json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function setJsonData(?string $jsonData): void
    {
        $jsonData ??= '{}';

        $this->data = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getAclOwnerId(): string
    {
        return '';
    }

    public function getTarget(): ?Target
    {
        return $this->target;
    }

    public function setTarget(Target $target): void
    {
        $this->target = $target;
    }
}
