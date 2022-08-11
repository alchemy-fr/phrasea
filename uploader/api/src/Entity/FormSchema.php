<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Entity\FormSchemaRepository")
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="uniq_target_locale", columns={"locale", "target_id"})})
 */
class FormSchema implements AclObjectInterface
{
    /**
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @Groups({"formschema:index"})
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Target")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotNull()
     * @Groups({"formschema:index", "formschema:write"})
     */
    private ?Target $target = null;

    /**
     * @ORM\Column(type="string", length=5, nullable=true)
     * @Groups({"formschema:index", "formschema:write"})
     */
    private ?string $locale = null;

    /**
     * @ORM\Column(type="json")
     * @Groups({"formschema:index", "formschema:write"})
     */
    private array $data = [];

    /**
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="create")
     * @Groups({"targetparams:index"})
     */
    private ?DateTimeInterface $createdAt = null;

    /**
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="update")
     */
    private ?DateTimeInterface $updatedAt = null;

    public function __construct(?string $id = null)
    {
        $this->id = null !== $id ? Uuid::fromString($id) : Uuid::uuid4();
    }

    public function getId(): string
    {
        return $this->id->__toString();
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
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function setJsonData(?string $jsonData): void
    {
        $jsonData ??= '{}';

        $this->data = \GuzzleHttp\json_decode($jsonData, true);
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
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
