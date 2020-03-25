<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle;

use Alchemy\OAuthServerBundle\DependencyInjection\AlchemyOAuthServerExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyOAuthServerBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new AlchemyOAuthServerExtension();
    }
}
