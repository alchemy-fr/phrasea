<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateTimePickerType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
                'widget' => 'single_text',
                'format' => "dd/MM/yyyy' 'HH:mm",
                'attr' => [
                    'class' => 'datetime-picker',
                ],
            ]
        );
    }

    public function getParent()
    {
        return DateTimeType::class;
    }
}
