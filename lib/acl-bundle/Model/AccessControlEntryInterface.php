<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Model;

interface AccessControlEntryInterface
{
    public function getId(): string;

    public function getUserId(): ?string;

    public function setUserId(string $userId): void;

    public function getObject(): ?string;

    public function setObject(string $object): void;

    public function getMask(): int;

    public function setMask(int $mask): void;

    public function setPermissions(array $permissions): void;

    public function getPermissions(): array;

    public function addPermission(int $permission): void;

    public function removePermission(int $permission): void;

    public function resetPermissions(): void;
}
