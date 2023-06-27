<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Form;

use Alchemy\RemoteAuthBundle\Client\AdminClient;
use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserChoiceType extends AbstractType
{
    public function __construct(private readonly AdminClient $adminClient, private readonly AuthServiceClient $authServiceClient)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        /** @var RemoteUser[] $users */
        $users = $this->adminClient->executeWithAccessToken(fn(string $accessToken): array => $this->authServiceClient->getUsers($accessToken));
        $choices = [];
        foreach ($users as $user) {
            $choices[$user['username']] = $user['id'];
        }

        $resolver->setDefaults([
                'choices' => $choices,
            ]
        );
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
