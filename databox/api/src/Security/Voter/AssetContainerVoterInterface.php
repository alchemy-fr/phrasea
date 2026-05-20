<?php

namespace App\Security\Voter;

interface AssetContainerVoterInterface
{
    final public const string ASSET_VIEW = 'ASSET_VIEW';
    final public const string ASSET_CREATE = 'ASSET_CREATE';
    final public const string ASSET_EDIT = 'ASSET_EDIT';
    final public const string ASSET_EDIT_ATTRIBUTES = 'ASSET_EDIT_ATTRIBUTES';
    final public const string ASSET_DELETE = 'ASSET_DELETE';
    final public const string ASSET_SHARE = 'ASSET_SHARE';
    final public const string ASSET_OWNER = 'ASSET_OWNER';
    final public const string ASSET_EDIT_PERMISSIONS = 'ASSET_EDIT_PERMISSIONS';
}
