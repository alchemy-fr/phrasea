<?php

namespace Alchemy\AdminBundle\Field;

use Alchemy\RemoteAuthBundle\Client\AdminClient;
use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

final readonly class UserChoiceField
{
    public function __construct(private AdminClient $adminClient, private AuthServiceClient $authServiceClient)
    {
    }

    public function create(string $propertyName, string $label = null)
    {
        /** @var RemoteUser[] $users */
        $users = $this->adminClient->executeWithAccessToken(fn (string $accessToken): array => $this->authServiceClient->getUsers($accessToken));
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
