<?php

declare(strict_types=1);

namespace App\Form;

use App\Model\MapOptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MapOptionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('lat', NumberType::class)
            ->add('lng', NumberType::class)
            ->add('zoom', NumberType::class)
            ->add('mapLayout', MapLayoutChoiceType::class, [
                'required' => false,
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('data_class', MapOptions::class)
        ;
    }
}
