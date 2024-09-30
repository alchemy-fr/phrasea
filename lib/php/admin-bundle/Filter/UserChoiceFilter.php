<?php

namespace Alchemy\AdminBundle\Filter;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use Alchemy\AuthBundle\Security\JwtUser;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

final readonly class UserChoiceFilter
{
    public function __construct(private ServiceAccountClient $serviceAccountClient, private KeycloakClient $authServiceClient)
    {
    }

    public function createFilter(string $propertyName, ?string $label = null):ChoiceFilter
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

        return ChoiceFilter::new($propertyName, $label)->setChoices($choices);
    }
}
