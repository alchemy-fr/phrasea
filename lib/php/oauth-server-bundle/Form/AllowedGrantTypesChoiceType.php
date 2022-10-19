<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AllowedGrantTypesChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        foreach ([
            'authorization_code',
            'password',
            'client_credentials',
            'refresh_token',
                 ] as $scope) {
            $choices[$scope] = $scope;
        }

        $resolver->setDefaults([
            'multiple' => true,
            'expanded' => true,
            'choices' => $choices,
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
