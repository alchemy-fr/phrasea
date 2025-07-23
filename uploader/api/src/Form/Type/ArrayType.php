<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArrayType extends AbstractType implements DataTransformerInterface
{
    /**
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $resizeListener = new ArrayResizeFormListener(
            $options['entry_type'],
            $options['entry_options'],
        );

        $builder->addEventSubscriber($resizeListener);
    }

    public function transform(mixed $value)
    {
        if (null === $value) {
            return [];
        }

        if (!is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        return $value;
    }

    public function reverseTransform(mixed $value)
    {
        if (null === $value) {
            return [];
        }

        if (!is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        // Ensure all values are strings, as TextType expects string values
        foreach ($value as $key => $item) {
            if (!is_string($item)) {
                throw new TransformationFailedException('All items in the array must be strings.');
            }
        }

        return $value;
    }

    /**
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'entry_type' => TextType::class,
            'entry_options' => [],
            'prototype' => [],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'array_';
    }
}
