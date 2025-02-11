<?php

namespace App\Entity\Traits;

interface ErrorDisableInterface
{
    public function getId(): string;

    public function getLastErrors(): array;

    public function appendError(array $error): void;

    public function setLastErrors(array $lastErrors): void;

    public function getErrorCount(): int;

    public function disableAfterErrors(): void;
}
