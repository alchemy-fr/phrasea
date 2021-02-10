<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PrivacyChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [
            'Secret' => WorkspaceItemPrivacyInterface::SECRET,
            'Private in workspace' => WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
            'Public in workspace' => WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE,
            'Private' => WorkspaceItemPrivacyInterface::PRIVATE,
            'Public for users' => WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS,
            'Public' => WorkspaceItemPrivacyInterface::PUBLIC,
        ];

        $resolver->setDefault('choices', $choices);
    }


    public function getParent()
    {
        return ChoiceType::class;
    }

}
