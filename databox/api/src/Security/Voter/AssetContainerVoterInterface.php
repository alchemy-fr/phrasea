<?php

namespace App\Security\Voter;

interface AssetContainerVoterInterface
{
    final public const string CREATE_ASSET = 'CREATE_ASSET';
    final public const string EDIT_ASSET = 'EDIT_ASSET';
    final public const string EDIT_ASSET_ATTRIBUTES = 'EDIT_ASSET_ATTRIBUTES';
    final public const string DELETE_ASSET = 'DELETE_ASSET';
    final public const string SHARE_ASSET = 'SHARE_ASSET';
}
