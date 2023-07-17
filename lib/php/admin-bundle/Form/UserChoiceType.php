<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Form;

use Alchemy\AuthBundle\Client\OAuthClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use Alchemy\AuthBundle\Model\RemoteUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserChoiceType extends AbstractType
{
    public function __construct(private readonly ServiceAccountClient $serviceAccountClient, private readonly OAuthClient $authServiceClient)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        /** @var RemoteUser[] $users */
        $users = $this->serviceAccountClient->executeWithAccessToken(fn (string $accessToken): array => $this->authServiceClient->getUsers($accessToken));
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
