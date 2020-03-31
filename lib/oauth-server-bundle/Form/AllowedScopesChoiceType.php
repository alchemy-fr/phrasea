<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AllowedScopesChoiceType extends AbstractType
{
    /**
     * @var array
     */
    private $scopes;

    public function __construct(array $scopes)
    {
        $this->scopes = $scopes;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        foreach ($this->scopes as $scope) {
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
