<?php

namespace Alchemy\AdminBundle\Field;

use Alchemy\RemoteAuthBundle\Client\AdminClient;
use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

final class UserChoiceField
{
    private AdminClient $adminClient;
    private AuthServiceClient $authServiceClient;

    public function __construct(AdminClient $adminClient, AuthServiceClient $authServiceClient)
    {
        $this->adminClient = $adminClient;
        $this->authServiceClient = $authServiceClient;
    }

    public function create(string $propertyName, ?string $label = null)
    {
        /** @var RemoteUser[] $users */
        $users = $this->adminClient->executeWithAccessToken(function (string $accessToken): array {
            return $this->authServiceClient->getUsers($accessToken);
        });
        $choices = [];
        foreach ($users as $user) {
            $choices[$user['username']] = $user['id'];
        }
        return ChoiceField::new($propertyName, $label)->setChoices($choices);
    }

}
