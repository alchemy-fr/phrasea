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

    public function getTopicKeys(): array;

    public static function getTopicKey(string $event): string;

    public function isAutoSubscribeOwner(): bool;

    public function novuTopicExists(string $topic): bool;

    public function setNovuTopicCreated(string $topic): void;

    public function getOwnerId(): ?string;
}
