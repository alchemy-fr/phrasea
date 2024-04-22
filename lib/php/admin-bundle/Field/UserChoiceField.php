<?php

namespace Alchemy\AdminBundle\Field;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use Alchemy\AuthBundle\Security\JwtUser;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

final readonly class UserChoiceField
{
    public function __construct(private ServiceAccountClient $serviceAccountClient, private KeycloakClient $authServiceClient)
    {
    }

    public function create(string $propertyName, ?string $label = null)
    {
        /** @var JwtUser[] $users */
        $users = $this->serviceAccountClient->executeWithAccessToken(fn (string $accessToken): array => $this->authServiceClient->getUsers($accessToken));
        $choices = [];
        foreach ($users as $user) {
            $choices[$user['username']] = $user['id'];
        }

        if (empty($choices)) {
            $choices = ['' => ''];
        }

        return ChoiceField::new($propertyName, $label)->setChoices($choices);
    }
}
