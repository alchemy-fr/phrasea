<?php

declare(strict_types=1);

namespace App\Form;

use Alchemy\AdminBundle\Form\DateTimePickerType;
use App\Entity\PublicationConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PublicationConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
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
            ->add('password', TextType::class, [
                'required' => false,
            ])
            ->add('layout', LayoutChoiceType::class)
            ->add('layoutOptions', LayoutOptionsType::class)
            ->add('theme', ThemeChoiceType::class)
            ->add('css', TextareaType::class)
            ->add('downloadEnabled', TrueFalseNullChoiceType::class)
            ->add('downloadViaEmail', TrueFalseNullChoiceType::class)
            ->add('downloadTerms', TermsConfigType::class)
            ->add('terms', TermsConfigType::class)
            ->add('includeDownloadTermsInZippy', TrueFalseNullChoiceType::class)
            ->add('mapOptions', MapOptionsType::class)
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('data_class', PublicationConfig::class)
        ;
    }
}
