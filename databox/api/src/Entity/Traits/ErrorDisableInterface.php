<?php

namespace App\Entity\Traits;

use App\Entity\Core\Workspace;

interface ErrorDisableInterface
{
    public function getId(): string;

    public function getLastErrors(): array;

    public function appendError(array $error): void;

    public function setLastErrors(array $lastErrors): void;

    public function getErrorCount(): int;

    public function disableAfterErrors(): void;

    public function getWorkspace(): ?Workspace;
}
