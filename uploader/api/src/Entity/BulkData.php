<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Entity\BulkDataRepository")
 */
class BulkData implements AclObjectInterface
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
     * @ORM\Column(type="json")
     */
    private array $data = [];

    /**
     * @ORM\Column(type="datetime")
     */
    private DateTime $createdAt;

    /**
     * @ORM\Column(type="datetime")
     */
    private DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getJsonData(): ?string
    {
        return \GuzzleHttp\json_encode($this->data);
    }

    public function setJsonData(string $jsonData): void
    {
        $this->data = \GuzzleHttp\json_decode($jsonData);
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

    public function setTarget(?Target $target): void
    {
        $this->target = $target;
    }
}
