<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Publication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SecurityMethodChoiceType extends AbstractType
{
    /**
     * @var array
     */
    private $choices;

    public function __construct()
    {
        $this->choices = [
            Publication::SECURITY_METHOD_PASSWORD,
            Publication::SECURITY_METHOD_AUTHENTICATION,
        ];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        foreach ($this->choices as $choice) {
            $choices[$choice] = $choice;
        }

        $resolver->setDefaults([
            'choices' => $choices,
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
