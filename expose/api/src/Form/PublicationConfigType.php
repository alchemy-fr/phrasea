<?php

declare(strict_types=1);

namespace App\Form;

use Alchemy\AdminBundle\Form\DateTimePickerType;
use App\Entity\PublicationConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PublicationConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', TrueFalseNullChoiceType::class)
            ->add('publiclyListed', TrueFalseNullChoiceType::class)
            ->add('beginsAt', DateTimePickerType::class, [
                'required' => false,
            ])
            ->add('expiresAt', DateTimePickerType::class, [
                'required' => false,
            ])
            ->add('securityMethod', SecurityMethodChoiceType::class, [
                'required' => false,
            ])
            // Stored hashed: never shown, only replaced when a new one is typed
            ->add('password', PasswordType::class, [
                'required' => false,
                'mapped' => false,
                'always_empty' => true,
                'help' => 'Leave empty to keep the current password.',
            ])
            ->add('layout', LayoutChoiceType::class)
            ->add('layoutOptions', LayoutOptionsType::class)
            ->add('theme', ThemeChoiceType::class)
            ->add('css', TextareaType::class)
            ->add('downloadEnabled', TrueFalseNullChoiceType::class)
            ->add('downloadViaEmail', TrueFalseNullChoiceType::class)
            ->add('downloadTerms', TermsConfigType::class)
            ->add('terms', TermsConfigType::class)
            ->add('includeDownloadTermsInArchive', TrueFalseNullChoiceType::class)
            ->add('mapOptions', MapOptionsType::class)
            ->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
                $config = $event->getData();
                $password = $event->getForm()->get('password')->getData();
                if ($config instanceof PublicationConfig && is_string($password) && '' !== $password) {
                    $config->setPassword($password);
                }
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('data_class', PublicationConfig::class)
        ;
    }
}
