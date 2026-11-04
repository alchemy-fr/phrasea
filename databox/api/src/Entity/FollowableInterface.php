<?php

declare(strict_types=1);

namespace App\Entity;

interface FollowableInterface
{
    public function getId(): string;

    /**
     * Events a user subscribes to when following this object.
     *
     * @return array<int, string>
     */
    public function getFollowEvents(): array;

    public function getObjectType(): string;

    public function isAutoSubscribeOwner(): bool;

    public function getOwnerId(): ?string;
}
