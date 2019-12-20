<?php

namespace App\Saml;

use App\Entity\User;
use Hslavich\OneloginSamlBundle\Security\Authentication\Token\SamlTokenInterface;
use Hslavich\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;

class SamlUserFactory implements SamlUserFactoryInterface
{
    public function createUser(SamlTokenInterface $token)
    {
        $user = new User();
        $user->setUsername($token->getUsername());

        return $user;
    }

    protected function getPropertyValue($token, $attribute)
    {
        if (is_string($attribute) && '$' == substr($attribute, 0, 1)) {
            $attributes = $token->getAttributes();

            return $attributes[substr($attribute, 1)][0];
        }

        return $attribute;
    }
}
