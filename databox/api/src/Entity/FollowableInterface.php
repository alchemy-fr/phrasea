<?php

namespace App\Entity;

interface FollowableInterface
{
    public function getId(): string;

    public function getTopicKeys(): array;

    public static function getTopicKey(string $event, string $id): string;

    public function isAutoSubscribeOwner(): bool;

    public function novuTopicExists(string $topic): bool;

    public function setNovuTopicCreated(string $topic): void;

    public function getOwnerId(): ?string;
}
