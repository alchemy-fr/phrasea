<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Core\Collection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollectionAssetType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('class', Collection::class);
        $resolver->setDefault('multiple', true);
        $resolver->setDefault('expanded', true);
    }

    public function getParent(): ?string
    {
        return EntityType::class;
    }
}
