<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JsonType extends AbstractType implements DataTransformerInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder->addModelTransformer($this);
    }

    public function transform(mixed $value)
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function reverseTransform(mixed $value)
    {
        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('attr', [
            'rows' => 10,
            'style' => 'font-family: "Courier New"',
        ]);
    }

    public function getParent(): ?string
    {
        return TextareaType::class;
    }
}
