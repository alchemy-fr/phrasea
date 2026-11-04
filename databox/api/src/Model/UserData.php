<?php

namespace App\Model;

final readonly class UserData implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public array $groupsId,
        public ?array $roles = [],
        public ?array $scopes = [],
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'groupsId' => $this->groupsId,
            'roles' => $this->roles,
            'scopes' => $this->scopes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['id'],
            (array) ($data['groupsId'] ?? []),
            (array) ($data['roles'] ?? []),
            (array) ($data['scopes'] ?? []),
        );
    }
}
