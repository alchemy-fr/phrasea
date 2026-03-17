<?php

namespace App\Security\Voter;

interface AssetContainerVoterInterface
{
    final public const string CREATE_ASSET = 'CREATE_ASSET';
    final public const string EDIT_ASSET = 'EDIT_ASSET';
    final public const string EDIT_ASSET_PRIVACY = 'EDIT_ASSET_PRIVACY';
    final public const string EDIT_ASSET_TAG = 'EDIT_ASSET_TAG';
    final public const string EDIT_ASSET_ATTRIBUTES = 'EDIT_ASSET_ATTRIBUTES';
    final public const string DELETE_ASSET = 'DELETE_ASSET';
    final public const string SHARE_ASSET = 'SHARE_ASSET';

    // Edit permissions and privacy of subject
    final public const int PERM_EDIT_PERMISSIONS = 1;
    final public const int PERM_EDIT_TAG = 2;
    final public const int PERM_MANAGE_USERS = 3;
    final public const int PERM_CREATE_ASSETS_IN_ONW_COLLECTION = 4;
}
