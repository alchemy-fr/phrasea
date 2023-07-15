<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Form;

use Alchemy\AuthBundle\Auth\ServiceAccountClient;
use Alchemy\AuthBundle\Client\AdminClient;
use Alchemy\AuthBundle\Client\OAuthClient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroupChoiceType extends AbstractType
{
    public function __construct(private readonly ServiceAccountClient $serviceAccountClient, private readonly OAuthClient $authServiceClient)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $groups = $this->serviceAccountClient->executeWithAccessToken(fn (string $accessToken): array => $this->authServiceClient->getGroups($accessToken));
        $choices = [];
        foreach ($groups as $group) {
            $choices[$group['name']] = $group['id'];
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
