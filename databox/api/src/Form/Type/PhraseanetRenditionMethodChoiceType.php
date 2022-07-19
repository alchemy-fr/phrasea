<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Core\Workspace;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PhraseanetRenditionMethodChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [
            'None' => null,
            'Enqueue' => Workspace::PHRASEANET_RENDITION_METHOD_ENQUEUE,
            'Subdef V3 API' => Workspace::PHRASEANET_RENDITION_METHOD_SUBDEF_V3_API,
        ];

        $resolver->setDefaults([
            'choices' => $choices,
            'placeholder' => 'Choose...',
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
