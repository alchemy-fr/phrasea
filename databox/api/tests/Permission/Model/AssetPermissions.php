<?php

namespace App\Tests\Permission\Model;

final readonly class AssetPermissions
{
    public function __construct(
        public bool $view = false,
        public bool $edit = false,
        public bool $editAttributes = false,
        public bool $editTags = false,
        public bool $editPrivacy = false,
        public bool $delete = false,
    ) {
    }
}
