<?php

namespace Alchemy\NotifyBundle\Form;

use Alchemy\NotifyBundle\Model\Notification;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NotifyForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            'All users' => null,
        ];
        foreach ($options['workspaces'] as $workspace) {
            $choices[$workspace->getName()] = 'ws:'.$workspace->getId();
        }

        $builder
            ->add('topic', ChoiceType::class, [
                'choices' => $choices,
            ])
            ->add('subject')
            ->add('content', TextareaType::class)
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Notification::class,
        ]);
        $resolver->setRequired([
            'workspaces',
        ]);
    }
}
