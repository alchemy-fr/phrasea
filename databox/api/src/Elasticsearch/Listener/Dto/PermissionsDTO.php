<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener\Dto;

final class PermissionsDTO
{
    public array $users = [];
    public array $groups = [];
    public array $deleteUsers = [];
    public array $deleteGroups = [];
    public array $quarantineUsers = [];
    public array $quarantineGroups = [];

    public function mergeWith(self $with, bool $onlyView = true): self
    {
        $this->users = array_merge($this->users, $with->users);
        $this->groups = array_merge($this->groups, $with->groups);

        if (!$onlyView) {
            $this->deleteUsers = array_merge($this->deleteUsers, $with->deleteUsers);
            $this->deleteGroups = array_merge($this->deleteGroups, $with->deleteGroups);
            $this->quarantineUsers = array_merge($this->quarantineUsers, $with->quarantineUsers);
            $this->quarantineGroups = array_merge($this->quarantineGroups, $with->quarantineGroups);
        }

        return $this;
    }

    public function unique(): self
    {
        $this->users = array_values(array_unique($this->users));
        $this->groups = array_values(array_unique($this->groups));
        $this->deleteUsers = array_values(array_unique($this->deleteUsers));
        $this->deleteGroups = array_values(array_unique($this->deleteGroups));
        $this->quarantineUsers = array_values(array_unique($this->quarantineUsers));
        $this->quarantineGroups = array_values(array_unique($this->quarantineGroups));

        return $this;
    }
}
