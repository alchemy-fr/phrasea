<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Entity\FormSchemaRepository")
 */
class FormSchema implements AclObjectInterface
{
    /**
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Target")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Target $target = null;

    /**
     * @ORM\Column(type="string", length=5, nullable=true, unique=true)
     */
    private ?string $locale = null;

    /**
     * @var string
     * @ORM\Column(type="text")
     */
    private $data;

    /**
     * @ORM\Column(type="datetime")
     */
    private DateTime $createdAt;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    public function __construct(?string $id = null)
    {
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
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
        return json_decode($this->data, true);
    }

    public function getJsonData(): ?string
    {
        return $this->data;
    }

    public function setJsonData(?string $jsonData): void
    {
        $jsonData ??= '{}';

        $this->data = $jsonData;
    }

    public function setData(array $data): void
    {
        $this->data = json_encode($data);
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
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
