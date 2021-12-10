<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Core\RenditionRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserTypeType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        foreach ([
                     'user' => RenditionRule::TYPE_USER,
                     'group' => RenditionRule::TYPE_GROUP,
                 ] as $name => $code) {
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
