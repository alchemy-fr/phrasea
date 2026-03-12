<?php

namespace App\Security\Voter;

interface AssetContainerVoterInterface
{
    final public const string CREATE_ASSET = 'CREATE_ASSET';
    final public const string EDIT_ASSET = 'EDIT_ASSET';
    final public const string EDIT_ASSET_PRIVACY = 'EDIT_ASSET_PRIVACY';
    final public const string EDIT_ASSET_TAG = 'EDIT_ASSET_TAG';
    final public const string EDIT_COLLECTION_PRIVACY = 'EDIT_COLLECTION_PRIVACY';
    final public const string EDIT_ASSET_ATTRIBUTES = 'EDIT_ASSET_ATTRIBUTES';
    final public const string DELETE_ASSET = 'DELETE_ASSET';
    final public const string SHARE_ASSET = 'SHARE_ASSET';

    final public const int PERM_EDIT_PRIVACY = 1;
    final public const int PERM_EDIT_TAG = 2;
}
