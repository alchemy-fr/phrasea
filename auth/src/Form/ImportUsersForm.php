<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ImportUsersForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FileType::class, [
                'attr' => [
                    'accept' => 'text/csv',
                ],
            ])
            ->add('invite', CheckboxType::class, [
                'label' => 'Invite users by email',
                'required' => false,
            ])

            ->add('submit', SubmitType::class, [
                'label' => 'Import',
            ]);
    }
}
