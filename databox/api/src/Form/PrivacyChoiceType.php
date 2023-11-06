<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PrivacyChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = array_flip(WorkspaceItemPrivacyInterface::LABELS);

        $resolver->setDefault('choices', $choices);
    }

    public function getParent(): ?string
    {
        return ChoiceType::class;
    }
}
