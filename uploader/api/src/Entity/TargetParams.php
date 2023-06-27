<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class TargetParams implements AclObjectInterface
{
    /**
     * @var Uuid
     *
     *
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['targetparams:index'])]
    protected $id;

    /**
     *
     *
     *
     * @ApiFilter(filterClass=SearchFilter::class, strategy="exact")
     */
    #[ORM\OneToOne(targetEntity: \App\Entity\Target::class, inversedBy: 'targetParams')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['targetparams:index', 'targetparams:write'])]
    #[Assert\NotNull]
    private ?Target $target = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['targetparams:index', 'targetparams:write'])]
    private array $data = [];

    /**
     *
     * @Gedmo\Timestampable(on="create")
     *
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['targetparams:index'])]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * @Gedmo\Timestampable(on="update")
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
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
        return \GuzzleHttp\json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function setJsonData(?string $jsonData): void
    {
        $jsonData ??= '{}';

        $this->data = \GuzzleHttp\json_decode($jsonData, true);
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
