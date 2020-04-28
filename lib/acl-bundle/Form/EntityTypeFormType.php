<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Form;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntityTypeFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        foreach (AccessControlEntry::ENTITY_TYPES as $name => $code) {
            $choices[$name] = $code;
        }

        $resolver->setDefaults([
            'choices' => $choices,
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
