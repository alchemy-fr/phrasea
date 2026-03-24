<?php

namespace App\Tests\Permission\Model;

final readonly class AssetPermissions
{
    public function __construct(
        public bool $view = false,
        public bool $edit = false,
        public bool $editAttributes = false,
        public bool $editTags = false,
        public bool $editPermissions = false,
        public bool $delete = false,
    ) {
    }
}
