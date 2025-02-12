<?php

namespace App\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait ErrorDisableTrait
{
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $lastErrors = null;

    public function getLastErrors(): array
    {
        return $this->lastErrors ?? [];
    }

    public function appendError(array $error): void
    {
        $this->lastErrors[] = $error;
    }

    public function setLastErrors(array $lastErrors): void
    {
        $this->lastErrors = $lastErrors;
    }

    public function getErrorCount(): int
    {
        return count($this->lastErrors ?? []);
    }

    public function clearErrors(): void
    {
        $this->lastErrors = null;
    }
}
