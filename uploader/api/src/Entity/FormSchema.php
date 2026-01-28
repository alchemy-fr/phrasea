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
use App\Security\Voter\FormDataEditorVoter;
use Doctrine\DBAL\Types\Types;
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
            name: 'get_form_schema_by_locale',
        ),
        new Get(security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")'),
        new Delete(security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")'),
        new Put(security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")'),
        new Post(security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")'),
        new GetCollection(security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")'),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_INDEX],
    ],
    denormalizationContext: [
        'groups' => [self::GROUP_WRITE],
    ]
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_target_locale', columns: ['locale', 'target_id'])]
#[ORM\Entity(repositoryClass: FormSchemaRepository::class)]
class FormSchema extends AbstractUuidEntity implements AclObjectInterface
{
    final public const int LOCALE_MODE_NO_LOCALE = 0;
    final public const int LOCALE_MODE_USE_UA = 1;
    final public const int LOCALE_MODE_FORCED = 2;

    public const string GROUP_INDEX = 'formschema:i';
    public const string GROUP_WRITE = 'formschema:w';

    #[ORM\ManyToOne(targetEntity: Target::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private ?Target $target = null;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private ?string $locale = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private int $localeMode = 0;

    #[ORM\Column(type: Types::JSON)]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private array $data = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['targetparams:index'])]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(?string $id = null)
    {
        parent::__construct($id);
    }

    #[Groups([self::GROUP_INDEX])]
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
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

    public function getLocaleMode(): int
    {
        return $this->localeMode;
    }

    public function setLocaleMode(int $localeMode): void
    {
        $this->localeMode = $localeMode;
    }
}
