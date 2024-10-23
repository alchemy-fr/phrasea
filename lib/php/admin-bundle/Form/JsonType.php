<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
        if (null !== $value && false === json_validate($value)) {
            return ['input-error' => 'Invalid JSON: '.json_last_error_msg()];
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'attr' => [
                'rows' => 10,
                'style' => 'font-family: "Courier New"',
            ],
            'constraints' => [
                new Assert\Callback(
                    function (mixed $data, ExecutionContextInterface $context) {
                        if (isset($data['input-error'])) {
                            $context
                            ->buildViolation($data['input-error'])
                            ->addViolation();
                        }
                    }
                ),
            ],
        ]);
    }

    public function getParent(): ?string
    {
        return TextareaType::class;
    }
}
