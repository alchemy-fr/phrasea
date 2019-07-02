<?php

declare(strict_types=1);

namespace App\OAuth\ResourceOwner;

use HWI\Bundle\OAuthBundle\OAuth\ResourceOwnerInterface as BaseResourceOwnerInterface;


interface ResourceOwnerInterface extends BaseResourceOwnerInterface
{
    public static function getTypeName(): string;
}
