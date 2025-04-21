<?php

namespace Alchemy\WebhookBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class WebhookToken extends AbstractToken
{
    public function __construct(array $roles = [])
    {
        parent::__construct($roles);
    }
}
