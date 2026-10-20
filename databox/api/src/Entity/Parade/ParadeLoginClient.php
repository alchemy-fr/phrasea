<?php

declare(strict_types=1);

namespace App\Entity\Parade;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'parade_login_client')]
#[ORM\UniqueConstraint(name: 'parade_login_client_page_uniq', columns: ['page'])]
#[ORM\Entity]
class ParadeLoginClient extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    final public const string ROOT_PAGE = '_root';

    #[ORM\Column(type: Types::STRING, length: 63, nullable: false)]
    private string $page;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $clientId;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $attributes = [];

    #[ORM\Column(type: Types::STRING, length: 20, nullable: false)]
    private string $status = 'pending';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $appliedAt = null;

    public function __construct(string $page, string $clientId)
    {
        parent::__construct();
        $this->page = $page;
        $this->clientId = $clientId;
    }

    public function getPage(): string
    {
        return $this->page;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function markApplied(): void
    {
        $this->status = 'applied';
        $this->error = null;
        $this->appliedAt = new \DateTimeImmutable();
    }

    public function markFailed(string $error): void
    {
        $this->status = 'failed';
        $this->error = $error;
    }

    public function __toString(): string
    {
        return $this->clientId;
    }
}
