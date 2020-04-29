<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Form;

use Alchemy\AclBundle\Security\PermissionInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PermissionFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        foreach (PermissionInterface::PERMISSIONS as $name => $permission) {
            $choices[$name] = $permission;
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
